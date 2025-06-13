<?php

namespace App\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\InventarioMovimiento;

class ReportesController extends BaseController
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
     * Dashboard de reportes
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Reportes y Estadísticas');
        
        // Obtener estadísticas generales
        $estadisticas = $this->obtenerEstadisticasGenerales();
        $this->view->setVar('estadisticas', $estadisticas);
        
        // Obtener datos para gráficos
        $ventasPorMes = $this->obtenerVentasPorMes();
        $this->view->setVar('ventasPorMes', $ventasPorMes);
        
        $productosMasVendidos = $this->obtenerProductosMasVendidos();
        $this->view->setVar('productosMasVendidos', $productosMasVendidos);
        
        $clientesActivos = $this->obtenerClientesActivos();
        $this->view->setVar('clientesActivos', $clientesActivos);
    }

    /**
     * Reporte de ventas
     */
    public function ventasAction()
    {
        $this->view->setVar('title', 'Reporte de Ventas');
        
        // Obtener parámetros de filtros
        $fechaDesde = $this->request->getQuery('fecha_desde', 'string', date('Y-m-01'));
        $fechaHasta = $this->request->getQuery('fecha_hasta', 'string', date('Y-m-d'));
        $estado = $this->request->getQuery('estado', 'string', '');
        $cliente = $this->request->getQuery('cliente', 'int', 0);
        
        // Construir consulta
        $conditions = ['DATE(fecha_pedido) >= ? AND DATE(fecha_pedido) <= ?'];
        $bind = [$fechaDesde, $fechaHasta];
        
        if (!empty($estado)) {
            $conditions[] = "estado_id = (SELECT id FROM estados_pedido WHERE nombre = ?)";
            $bind[] = $estado;
        }
        
        if ($cliente > 0) {
            $conditions[] = "cliente_id = ?";
            $bind[] = $cliente;
        }
        
        // Obtener pedidos
        $pedidos = Pedido::find([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'fecha_pedido DESC'
        ]);
        
        // Calcular totales
        $totalVentas = 0;
        $totalPedidos = count($pedidos);
        $totalProductos = 0;
        
        foreach ($pedidos as $pedido) {
            $totalVentas += $pedido->total;
            $totalProductos += $pedido->countDetalles();
        }
        
        $this->view->setVar('pedidos', $pedidos);
        $this->view->setVar('totalVentas', $totalVentas);
        $this->view->setVar('totalPedidos', $totalPedidos);
        $this->view->setVar('totalProductos', $totalProductos);
        $this->view->setVar('fechaDesde', $fechaDesde);
        $this->view->setVar('fechaHasta', $fechaHasta);
        $this->view->setVar('estadoSeleccionado', $estado);
        $this->view->setVar('clienteSeleccionado', $cliente);
        
        // Obtener clientes para filtro
        $clientes = Usuario::find([
            'conditions' => 'rol_id = 3 AND activo = true',
            'order' => 'nombre ASC',
            'limit' => 100
        ]);
        $this->view->setVar('clientes', $clientes);
    }

    /**
     * Reporte de inventario
     */
    public function inventarioAction()
    {
        $this->view->setVar('title', 'Reporte de Inventario');
        
        // Obtener productos con información de stock
        $productos = Producto::find([
            'conditions' => 'activo = true',
            'order' => 'stock_actual ASC, nombre ASC'
        ]);
        
        // Calcular estadísticas de inventario
        $totalProductos = count($productos);
        $productosStockBajo = 0;
        $productosSinStock = 0;
        $valorTotalInventario = 0;
        
        foreach ($productos as $producto) {
            if ($producto->stock_actual <= $producto->stock_minimo) {
                $productosStockBajo++;
            }
            if ($producto->stock_actual == 0) {
                $productosSinStock++;
            }
            $valorTotalInventario += $producto->stock_actual * $producto->precio_compra;
        }
        
        $this->view->setVar('productos', $productos);
        $this->view->setVar('totalProductos', $totalProductos);
        $this->view->setVar('productosStockBajo', $productosStockBajo);
        $this->view->setVar('productosSinStock', $productosSinStock);
        $this->view->setVar('valorTotalInventario', $valorTotalInventario);
        
        // Obtener movimientos recientes
        $movimientosRecientes = InventarioMovimiento::find([
            'order' => 'created_at DESC',
            'limit' => 20
        ]);
        $this->view->setVar('movimientosRecientes', $movimientosRecientes);
    }

    /**
     * Reporte de clientes
     */
    public function clientesAction()
    {
        $this->view->setVar('title', 'Reporte de Clientes');
        
        // Obtener clientes con estadísticas
        $db = $this->getDI()->getDb();
        
        $clientesConEstadisticas = $db->fetchAll("
            SELECT 
                u.id,
                u.nombre,
                u.apellido,
                u.email,
                u.created_at,
                COALESCE(COUNT(p.id), 0) as total_pedidos,
                COALESCE(SUM(p.total), 0) as total_gastado,
                MAX(p.fecha_pedido) as ultima_compra
            FROM usuarios u
            LEFT JOIN pedidos p ON u.id = p.cliente_id
            WHERE u.rol_id = 3 AND u.activo = true
            GROUP BY u.id, u.nombre, u.apellido, u.email, u.created_at
            ORDER BY total_gastado DESC
        ");
        
        $this->view->setVar('clientes', $clientesConEstadisticas);
        
        // Estadísticas generales de clientes
        $totalClientes = count($clientesConEstadisticas);
        $clientesActivos = 0;
        $clientesInactivos = 0;
        
        foreach ($clientesConEstadisticas as $cliente) {
            if ($cliente['total_pedidos'] > 0) {
                $clientesActivos++;
            } else {
                $clientesInactivos++;
            }
        }
        
        $this->view->setVar('totalClientes', $totalClientes);
        $this->view->setVar('clientesActivos', $clientesActivos);
        $this->view->setVar('clientesInactivos', $clientesInactivos);
    }

    /**
     * Exportar reporte a CSV
     */
    public function exportarAction()
    {
        $tipo = $this->dispatcher->getParam('tipo');
        
        switch ($tipo) {
            case 'ventas':
                return $this->exportarVentas();
            case 'inventario':
                return $this->exportarInventario();
            case 'clientes':
                return $this->exportarClientes();
            default:
                $this->flashSession->error('Tipo de reporte no válido');
                return $this->response->redirect('/admin/reportes');
        }
    }

    /**
     * Obtener estadísticas generales
     */
    private function obtenerEstadisticasGenerales()
    {
        $db = $this->getDI()->getDb();
        
        // Ventas del mes actual
        $ventasMes = $db->fetchOne("
            SELECT COALESCE(SUM(total), 0) as total
            FROM pedidos 
            WHERE EXTRACT(MONTH FROM fecha_pedido) = EXTRACT(MONTH FROM CURRENT_DATE)
            AND EXTRACT(YEAR FROM fecha_pedido) = EXTRACT(YEAR FROM CURRENT_DATE)
        ");
        
        // Pedidos del día
        $pedidosHoy = Pedido::count([
            'conditions' => 'DATE(fecha_pedido) = CURRENT_DATE'
        ]);
        
        // Total de productos
        $totalProductos = Producto::count([
            'conditions' => 'activo = true'
        ]);
        
        // Total de clientes
        $totalClientes = Usuario::count([
            'conditions' => 'rol_id = 3 AND activo = true'
        ]);
        
        // Productos con stock bajo
        $productosStockBajo = Producto::count([
            'conditions' => 'stock_actual <= stock_minimo AND activo = true'
        ]);
        
        return [
            'ventas_mes' => $ventasMes['total'],
            'pedidos_hoy' => $pedidosHoy,
            'total_productos' => $totalProductos,
            'total_clientes' => $totalClientes,
            'productos_stock_bajo' => $productosStockBajo
        ];
    }

    /**
     * Obtener ventas por mes (últimos 12 meses)
     */
    private function obtenerVentasPorMes()
    {
        $db = $this->getDI()->getDb();
        
        return $db->fetchAll("
            SELECT 
                TO_CHAR(fecha_pedido, 'YYYY-MM') as mes,
                COUNT(*) as pedidos,
                COALESCE(SUM(total), 0) as total
            FROM pedidos 
            WHERE fecha_pedido >= CURRENT_DATE - INTERVAL '12 months'
            GROUP BY TO_CHAR(fecha_pedido, 'YYYY-MM')
            ORDER BY mes ASC
        ");
    }

    /**
     * Obtener productos más vendidos
     */
    private function obtenerProductosMasVendidos()
    {
        $db = $this->getDI()->getDb();
        
        return $db->fetchAll("
            SELECT 
                p.nombre,
                p.sku,
                SUM(pd.cantidad) as total_vendido,
                SUM(pd.subtotal) as total_ingresos
            FROM productos p
            INNER JOIN pedido_detalles pd ON p.id = pd.producto_id
            INNER JOIN pedidos pe ON pd.pedido_id = pe.id
            WHERE pe.fecha_pedido >= CURRENT_DATE - INTERVAL '30 days'
            GROUP BY p.id, p.nombre, p.sku
            ORDER BY total_vendido DESC
            LIMIT 10
        ");
    }

    /**
     * Obtener clientes más activos
     */
    private function obtenerClientesActivos()
    {
        $db = $this->getDI()->getDb();
        
        return $db->fetchAll("
            SELECT 
                u.nombre,
                u.apellido,
                u.email,
                COUNT(p.id) as total_pedidos,
                SUM(p.total) as total_gastado
            FROM usuarios u
            INNER JOIN pedidos p ON u.id = p.cliente_id
            WHERE p.fecha_pedido >= CURRENT_DATE - INTERVAL '30 days'
            GROUP BY u.id, u.nombre, u.apellido, u.email
            ORDER BY total_gastado DESC
            LIMIT 10
        ");
    }

    /**
     * Exportar reporte de ventas a CSV
     */
    private function exportarVentas()
    {
        // Implementar exportación de ventas
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="reporte_ventas_' . date('Y-m-d') . '.csv"');
        
        // Generar CSV
        $csv = "Número Pedido,Cliente,Fecha,Total,Estado\n";
        
        $pedidos = Pedido::find([
            'order' => 'fecha_pedido DESC',
            'limit' => 1000
        ]);
        
        foreach ($pedidos as $pedido) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $pedido->numero_pedido,
                $pedido->cliente ? $pedido->cliente->getNombreCompleto() : 'N/A',
                $pedido->fecha_pedido,
                $pedido->total,
                $pedido->estado ? $pedido->estado->nombre : 'N/A'
            );
        }
        
        return $this->response->setContent($csv);
    }

    /**
     * Exportar reporte de inventario a CSV
     */
    private function exportarInventario()
    {
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="reporte_inventario_' . date('Y-m-d') . '.csv"');
        
        $csv = "SKU,Nombre,Categoría,Stock Actual,Stock Mínimo,Precio Compra,Precio Venta\n";
        
        $productos = Producto::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC'
        ]);
        
        foreach ($productos as $producto) {
            $csv .= sprintf(
                "%s,%s,%s,%d,%d,%s,%s\n",
                $producto->sku,
                $producto->nombre,
                $producto->categoria ? $producto->categoria->nombre : 'N/A',
                $producto->stock_actual,
                $producto->stock_minimo,
                $producto->precio_compra,
                $producto->precio_venta
            );
        }
        
        return $this->response->setContent($csv);
    }

    /**
     * Exportar reporte de clientes a CSV
     */
    private function exportarClientes()
    {
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="reporte_clientes_' . date('Y-m-d') . '.csv"');
        
        $csv = "Nombre,Email,Teléfono,Ciudad,Fecha Registro,Total Pedidos,Total Gastado\n";
        
        $db = $this->getDI()->getDb();
        $clientes = $db->fetchAll("
            SELECT 
                u.*,
                COALESCE(COUNT(p.id), 0) as total_pedidos,
                COALESCE(SUM(p.total), 0) as total_gastado
            FROM usuarios u
            LEFT JOIN pedidos p ON u.id = p.cliente_id
            WHERE u.rol_id = 3 AND u.activo = true
            GROUP BY u.id
            ORDER BY u.nombre ASC
        ");
        
        foreach ($clientes as $cliente) {
            $csv .= sprintf(
                "%s %s,%s,%s,%s,%s,%d,%s\n",
                $cliente['nombre'],
                $cliente['apellido'],
                $cliente['email'],
                $cliente['telefono'] ?: 'N/A',
                $cliente['ciudad'] ?: 'N/A',
                $cliente['created_at'],
                $cliente['total_pedidos'],
                $cliente['total_gastado']
            );
        }
        
        return $this->response->setContent($csv);
    }
}

