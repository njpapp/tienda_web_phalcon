<?php

namespace App\Controllers;

use App\Models\Producto;
use App\Models\InventarioMovimiento;
use App\Models\Categoria;

class InventarioController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Verificar que el usuario puede acceder al panel administrativo
        if (!$this->requireAdminAccess()) {
            return false;
        }
    }

    /**
     * Vista principal del inventario
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Control de Inventario');
        
        // Obtener parámetros de búsqueda y filtros
        $busqueda = $this->request->getQuery('busqueda', 'string', '');
        $categoria = $this->request->getQuery('categoria', 'int', 0);
        $stockBajo = $this->request->getQuery('stock_bajo', 'int', 0);
        $paginacion = $this->obtenerPaginacion();
        
        // Construir consulta
        $conditions = ['activo = true'];
        $bind = [];
        
        if (!empty($busqueda)) {
            $conditions[] = "(nombre ILIKE ?1 OR sku ILIKE ?1)";
            $bind[1] = "%{$busqueda}%";
        }
        
        if ($categoria > 0) {
            $conditions[] = "categoria_id = ?2";
            $bind[2] = $categoria;
        }
        
        if ($stockBajo) {
            $conditions[] = "stock_actual <= stock_minimo";
        }
        
        // Obtener productos con información de stock
        $productos = Producto::find([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'stock_actual ASC, nombre ASC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalProductos = Producto::count([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind
        ]);
        
        $this->view->setVar('productos', $productos);
        $this->view->setVar('totalProductos', $totalProductos);
        $this->view->setVar('paginacion', $paginacion);
        $this->view->setVar('busqueda', $busqueda);
        $this->view->setVar('categoriaSeleccionada', $categoria);
        $this->view->setVar('stockBajo', $stockBajo);
        
        // Obtener categorías para filtros
        $categorias = Categoria::obtenerActivas();
        $this->view->setVar('categorias', $categorias);
        
        // Estadísticas generales
        $estadisticas = $this->obtenerEstadisticasInventario();
        $this->view->setVar('estadisticas', $estadisticas);
    }

    /**
     * Historial de movimientos de inventario
     */
    public function movimientosAction()
    {
        $this->view->setVar('title', 'Movimientos de Inventario');
        
        // Obtener parámetros de filtros
        $productoId = $this->request->getQuery('producto_id', 'int', 0);
        $tipoMovimiento = $this->request->getQuery('tipo', 'string', '');
        $fechaDesde = $this->request->getQuery('fecha_desde', 'string', '');
        $fechaHasta = $this->request->getQuery('fecha_hasta', 'string', '');
        $paginacion = $this->obtenerPaginacion();
        
        // Construir consulta
        $conditions = [];
        $bind = [];
        
        if ($productoId > 0) {
            $conditions[] = "producto_id = ?1";
            $bind[1] = $productoId;
        }
        
        if (!empty($tipoMovimiento)) {
            $conditions[] = "tipo_movimiento = ?2";
            $bind[2] = $tipoMovimiento;
        }
        
        if (!empty($fechaDesde)) {
            $conditions[] = "DATE(created_at) >= ?3";
            $bind[3] = $fechaDesde;
        }
        
        if (!empty($fechaHasta)) {
            $conditions[] = "DATE(created_at) <= ?4";
            $bind[4] = $fechaHasta;
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
        
        // Obtener movimientos
        $movimientos = InventarioMovimiento::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'created_at DESC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalMovimientos = InventarioMovimiento::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        $this->view->setVar('movimientos', $movimientos);
        $this->view->setVar('totalMovimientos', $totalMovimientos);
        $this->view->setVar('paginacion', $paginacion);
        $this->view->setVar('productoSeleccionado', $productoId);
        $this->view->setVar('tipoSeleccionado', $tipoMovimiento);
        $this->view->setVar('fechaDesde', $fechaDesde);
        $this->view->setVar('fechaHasta', $fechaHasta);
        
        // Obtener productos para filtro
        $productos = Producto::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC',
            'limit' => 100
        ]);
        $this->view->setVar('productos', $productos);
    }

    /**
     * Ajustar stock de un producto
     */
    public function ajustarAction()
    {
        if (!$this->requirePermiso('gestionar_inventario')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $productoId = $this->request->getPost('producto_id', 'int');
        $nuevoStock = $this->request->getPost('nuevo_stock', 'int');
        $motivo = $this->request->getPost('motivo', 'string', 'Ajuste manual');

        // Validar datos
        if (!$productoId || $nuevoStock < 0) {
            return $this->jsonError('Datos inválidos');
        }

        // Buscar el producto
        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$productoId]
        ]);

        if (!$producto) {
            return $this->jsonError('Producto no encontrado');
        }

        // Actualizar stock
        if ($producto->actualizarStock($nuevoStock, $motivo, $this->usuario->id)) {
            $this->registrarActividad('adjust_stock', "Stock ajustado para {$producto->nombre}: {$nuevoStock}", [
                'producto_id' => $producto->id,
                'stock_anterior' => $producto->stock_actual,
                'stock_nuevo' => $nuevoStock
            ]);
            
            return $this->jsonSuccess('Stock actualizado exitosamente', [
                'nuevo_stock' => $nuevoStock,
                'producto' => $producto->nombre
            ]);
        } else {
            return $this->jsonError('Error al actualizar el stock');
        }
    }

    /**
     * Alertas de stock bajo
     */
    public function alertasAction()
    {
        $this->view->setVar('title', 'Alertas de Stock');
        
        // Obtener productos con stock bajo
        $productosStockBajo = Producto::find([
            'conditions' => 'stock_actual <= stock_minimo AND activo = true',
            'order' => 'stock_actual ASC, nombre ASC'
        ]);
        
        $this->view->setVar('productosStockBajo', $productosStockBajo);
        
        // Obtener productos sin stock
        $productosSinStock = Producto::find([
            'conditions' => 'stock_actual = 0 AND activo = true',
            'order' => 'nombre ASC'
        ]);
        
        $this->view->setVar('productosSinStock', $productosSinStock);
        
        // Estadísticas de alertas
        $totalStockBajo = count($productosStockBajo);
        $totalSinStock = count($productosSinStock);
        $totalProductosActivos = Producto::count(['conditions' => 'activo = true']);
        
        $this->view->setVar('totalStockBajo', $totalStockBajo);
        $this->view->setVar('totalSinStock', $totalSinStock);
        $this->view->setVar('totalProductosActivos', $totalProductosActivos);
        $this->view->setVar('porcentajeAlertas', $totalProductosActivos > 0 ? ($totalStockBajo / $totalProductosActivos) * 100 : 0);
    }

    /**
     * Entrada de mercancía
     */
    public function entradaAction()
    {
        if (!$this->requirePermiso('gestionar_inventario')) {
            return false;
        }
        
        if ($this->request->isPost()) {
            $productoId = $this->request->getPost('producto_id', 'int');
            $cantidad = $this->request->getPost('cantidad', 'int');
            $motivo = $this->request->getPost('motivo', 'string', 'Entrada de mercancía');
            $referencia = $this->request->getPost('referencia', 'string');

            // Validar datos
            if (!$productoId || $cantidad <= 0) {
                $this->flashSession->error('Datos inválidos');
                return $this->dispatcher->forward(['action' => 'entrada']);
            }

            // Buscar el producto
            $producto = Producto::findFirst([
                'conditions' => 'id = ? AND activo = true',
                'bind' => [$productoId]
            ]);

            if (!$producto) {
                $this->flashSession->error('Producto no encontrado');
                return $this->dispatcher->forward(['action' => 'entrada']);
            }

            // Registrar entrada
            if ($producto->aumentarStock($cantidad, $motivo, $referencia, $this->usuario->id)) {
                $this->flashSession->success("Entrada registrada: +{$cantidad} unidades de {$producto->nombre}");
                $this->registrarActividad('stock_entry', "Entrada de mercancía: {$producto->nombre} +{$cantidad}", [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad
                ]);
                return $this->response->redirect('/admin/inventario/entrada');
            } else {
                $this->flashSession->error('Error al registrar la entrada');
            }
        }

        $this->view->setVar('title', 'Entrada de Mercancía');
        
        // Obtener productos para el formulario
        $productos = Producto::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC'
        ]);
        $this->view->setVar('productos', $productos);
        
        // Obtener entradas recientes
        $entradasRecientes = InventarioMovimiento::find([
            'conditions' => "tipo_movimiento = 'entrada'",
            'order' => 'created_at DESC',
            'limit' => 10
        ]);
        $this->view->setVar('entradasRecientes', $entradasRecientes);
    }

    /**
     * Salida de mercancía
     */
    public function salidaAction()
    {
        if (!$this->requirePermiso('gestionar_inventario')) {
            return false;
        }
        
        if ($this->request->isPost()) {
            $productoId = $this->request->getPost('producto_id', 'int');
            $cantidad = $this->request->getPost('cantidad', 'int');
            $motivo = $this->request->getPost('motivo', 'string', 'Salida de mercancía');
            $referencia = $this->request->getPost('referencia', 'string');

            // Validar datos
            if (!$productoId || $cantidad <= 0) {
                $this->flashSession->error('Datos inválidos');
                return $this->dispatcher->forward(['action' => 'salida']);
            }

            // Buscar el producto
            $producto = Producto::findFirst([
                'conditions' => 'id = ? AND activo = true',
                'bind' => [$productoId]
            ]);

            if (!$producto) {
                $this->flashSession->error('Producto no encontrado');
                return $this->dispatcher->forward(['action' => 'salida']);
            }

            // Verificar stock disponible
            if (!$producto->tieneStock($cantidad)) {
                $this->flashSession->error("Stock insuficiente. Disponible: {$producto->stock_actual}");
                return $this->dispatcher->forward(['action' => 'salida']);
            }

            // Registrar salida
            if ($producto->reducirStock($cantidad, $motivo, $referencia, $this->usuario->id)) {
                $this->flashSession->success("Salida registrada: -{$cantidad} unidades de {$producto->nombre}");
                $this->registrarActividad('stock_exit', "Salida de mercancía: {$producto->nombre} -{$cantidad}", [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad
                ]);
                return $this->response->redirect('/admin/inventario/salida');
            } else {
                $this->flashSession->error('Error al registrar la salida');
            }
        }

        $this->view->setVar('title', 'Salida de Mercancía');
        
        // Obtener productos para el formulario
        $productos = Producto::find([
            'conditions' => 'activo = true AND stock_actual > 0',
            'order' => 'nombre ASC'
        ]);
        $this->view->setVar('productos', $productos);
        
        // Obtener salidas recientes
        $salidasRecientes = InventarioMovimiento::find([
            'conditions' => "tipo_movimiento = 'salida'",
            'order' => 'created_at DESC',
            'limit' => 10
        ]);
        $this->view->setVar('salidasRecientes', $salidasRecientes);
    }

    /**
     * Obtener stock de un producto (AJAX)
     */
    public function obtenerStockAction()
    {
        $productoId = $this->request->getQuery('producto_id', 'int');
        
        if (!$productoId) {
            return $this->jsonError('ID de producto requerido');
        }

        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$productoId]
        ]);

        if (!$producto) {
            return $this->jsonError('Producto no encontrado');
        }

        return $this->jsonSuccess('Stock obtenido', [
            'stock_actual' => $producto->stock_actual,
            'stock_minimo' => $producto->stock_minimo,
            'stock_maximo' => $producto->stock_maximo,
            'nombre' => $producto->nombre,
            'sku' => $producto->sku,
            'stock_bajo' => $producto->stockBajo()
        ]);
    }

    /**
     * Obtiene estadísticas generales del inventario
     */
    private function obtenerEstadisticasInventario()
    {
        $db = $this->getDI()->getDb();
        
        // Total de productos activos
        $totalProductos = Producto::count(['conditions' => 'activo = true']);
        
        // Productos con stock bajo
        $productosStockBajo = Producto::count(['conditions' => 'stock_actual <= stock_minimo AND activo = true']);
        
        // Productos sin stock
        $productosSinStock = Producto::count(['conditions' => 'stock_actual = 0 AND activo = true']);
        
        // Valor total del inventario
        $valorInventario = $db->fetchOne(
            "SELECT COALESCE(SUM(stock_actual * precio_compra), 0) as valor_total 
             FROM productos 
             WHERE activo = true"
        );
        
        // Movimientos del día
        $movimientosHoy = InventarioMovimiento::count([
            'conditions' => 'DATE(created_at) = CURRENT_DATE'
        ]);
        
        return [
            'total_productos' => $totalProductos,
            'productos_stock_bajo' => $productosStockBajo,
            'productos_sin_stock' => $productosSinStock,
            'valor_inventario' => $valorInventario['valor_total'],
            'movimientos_hoy' => $movimientosHoy,
            'porcentaje_stock_bajo' => $totalProductos > 0 ? ($productosStockBajo / $totalProductos) * 100 : 0
        ];
    }
}

