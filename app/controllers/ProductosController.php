<?php

namespace App\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\ProductoImagen;

class ProductosController extends BaseController
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
     * Lista de productos
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Gestión de Productos');
        
        // Obtener parámetros de búsqueda y filtros
        $busqueda = $this->request->getQuery('busqueda', 'string', '');
        $categoria = $this->request->getQuery('categoria', 'int', 0);
        $proveedor = $this->request->getQuery('proveedor', 'int', 0);
        $stockBajo = $this->request->getQuery('stock_bajo', 'int', 0);
        $paginacion = $this->obtenerPaginacion();
        
        // Construir consulta
        $conditions = ['activo = true'];
        $bind = [];
        
        if (!empty($busqueda)) {
            $conditions[] = "(nombre ILIKE ?1 OR sku ILIKE ?1 OR descripcion ILIKE ?1)";
            $bind[1] = "%{$busqueda}%";
        }
        
        if ($categoria > 0) {
            $conditions[] = "categoria_id = ?2";
            $bind[2] = $categoria;
        }
        
        if ($proveedor > 0) {
            $conditions[] = "proveedor_id = ?3";
            $bind[3] = $proveedor;
        }
        
        if ($stockBajo) {
            $conditions[] = "stock_actual <= stock_minimo";
        }
        
        // Obtener productos
        $productos = Producto::find([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'created_at DESC',
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
        $this->view->setVar('proveedorSeleccionado', $proveedor);
        $this->view->setVar('stockBajo', $stockBajo);
        
        // Obtener categorías y proveedores para filtros
        $categorias = Categoria::obtenerActivas();
        $proveedores = Proveedor::obtenerActivos();
        $this->view->setVar('categorias', $categorias);
        $this->view->setVar('proveedores', $proveedores);
    }

    /**
     * Crear nuevo producto
     */
    public function crearAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        $this->view->setVar('title', 'Crear Producto');
        
        // Obtener categorías y proveedores
        $categorias = Categoria::obtenerActivas();
        $proveedores = Proveedor::obtenerActivos();
        $this->view->setVar('categorias', $categorias);
        $this->view->setVar('proveedores', $proveedores);
    }

    /**
     * Guardar nuevo producto
     */
    public function guardarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/productos');
        }

        $datos = [
            'sku' => $this->request->getPost('sku', 'string'),
            'nombre' => $this->request->getPost('nombre', 'string'),
            'descripcion' => $this->request->getPost('descripcion', 'string'),
            'categoria_id' => $this->request->getPost('categoria_id', 'int'),
            'proveedor_id' => $this->request->getPost('proveedor_id', 'int'),
            'precio_compra' => $this->request->getPost('precio_compra', 'float'),
            'precio_venta' => $this->request->getPost('precio_venta', 'float'),
            'stock_actual' => $this->request->getPost('stock_actual', 'int', 0),
            'stock_minimo' => $this->request->getPost('stock_minimo', 'int', 0),
            'stock_maximo' => $this->request->getPost('stock_maximo', 'int', 1000),
            'unidad_medida' => $this->request->getPost('unidad_medida', 'string', 'unidad'),
            'peso' => $this->request->getPost('peso', 'float'),
            'dimensiones' => $this->request->getPost('dimensiones', 'string')
        ];

        // Validar datos
        $errores = $this->validarProducto($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            $categorias = Categoria::obtenerActivas();
            $proveedores = Proveedor::obtenerActivos();
            $this->view->setVar('categorias', $categorias);
            $this->view->setVar('proveedores', $proveedores);
            
            return $this->dispatcher->forward([
                'action' => 'crear'
            ]);
        }

        // Crear producto
        $producto = new Producto();
        $producto->sku = $datos['sku'];
        $producto->nombre = $datos['nombre'];
        $producto->descripcion = $datos['descripcion'];
        $producto->categoria_id = $datos['categoria_id'];
        $producto->proveedor_id = $datos['proveedor_id'];
        $producto->precio_compra = $datos['precio_compra'];
        $producto->precio_venta = $datos['precio_venta'];
        $producto->stock_actual = $datos['stock_actual'];
        $producto->stock_minimo = $datos['stock_minimo'];
        $producto->stock_maximo = $datos['stock_maximo'];
        $producto->unidad_medida = $datos['unidad_medida'];
        $producto->peso = $datos['peso'];
        $producto->dimensiones = $datos['dimensiones'];

        if ($producto->save()) {
            // Registrar movimiento inicial de inventario si hay stock
            if ($datos['stock_actual'] > 0) {
                $producto->aumentarStock(
                    $datos['stock_actual'], 
                    'Stock inicial', 
                    null, 
                    $this->usuario->id
                );
            }

            $this->flashSession->success('Producto creado exitosamente');
            $this->registrarActividad('create_product', "Producto creado: {$producto->nombre}", ['producto_id' => $producto->id]);
            return $this->response->redirect('/admin/productos');
        } else {
            $this->flashSession->error('Error al crear el producto');
            $errores = [];
            foreach ($producto->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            $categorias = Categoria::obtenerActivas();
            $proveedores = Proveedor::obtenerActivos();
            $this->view->setVar('categorias', $categorias);
            $this->view->setVar('proveedores', $proveedores);
            
            return $this->dispatcher->forward([
                'action' => 'crear'
            ]);
        }
    }

    /**
     * Editar producto existente
     */
    public function editarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        $id = $this->dispatcher->getParam('id');
        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$producto) {
            $this->flashSession->error('Producto no encontrado');
            return $this->response->redirect('/admin/productos');
        }

        $this->view->setVar('title', 'Editar Producto');
        $this->view->setVar('producto', $producto);
        
        // Obtener categorías y proveedores
        $categorias = Categoria::obtenerActivas();
        $proveedores = Proveedor::obtenerActivos();
        $this->view->setVar('categorias', $categorias);
        $this->view->setVar('proveedores', $proveedores);
        
        // Obtener imágenes del producto
        $imagenes = ProductoImagen::obtenerPorProducto($id);
        $this->view->setVar('imagenes', $imagenes);
    }

    /**
     * Actualizar producto existente
     */
    public function actualizarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/productos');
        }

        $id = $this->dispatcher->getParam('id');
        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$producto) {
            $this->flashSession->error('Producto no encontrado');
            return $this->response->redirect('/admin/productos');
        }

        $datos = [
            'sku' => $this->request->getPost('sku', 'string'),
            'nombre' => $this->request->getPost('nombre', 'string'),
            'descripcion' => $this->request->getPost('descripcion', 'string'),
            'categoria_id' => $this->request->getPost('categoria_id', 'int'),
            'proveedor_id' => $this->request->getPost('proveedor_id', 'int'),
            'precio_compra' => $this->request->getPost('precio_compra', 'float'),
            'precio_venta' => $this->request->getPost('precio_venta', 'float'),
            'stock_minimo' => $this->request->getPost('stock_minimo', 'int'),
            'stock_maximo' => $this->request->getPost('stock_maximo', 'int'),
            'unidad_medida' => $this->request->getPost('unidad_medida', 'string'),
            'peso' => $this->request->getPost('peso', 'float'),
            'dimensiones' => $this->request->getPost('dimensiones', 'string'),
            'activo' => $this->request->getPost('activo', 'int', 1)
        ];

        // Validar datos (sin validar SKU duplicado para el mismo producto)
        $errores = $this->validarProducto($datos, false, $producto->id);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('producto', $producto);
            
            $categorias = Categoria::obtenerActivas();
            $proveedores = Proveedor::obtenerActivos();
            $this->view->setVar('categorias', $categorias);
            $this->view->setVar('proveedores', $proveedores);
            
            return $this->dispatcher->forward([
                'action' => 'editar'
            ]);
        }

        // Actualizar producto
        $producto->sku = $datos['sku'];
        $producto->nombre = $datos['nombre'];
        $producto->descripcion = $datos['descripcion'];
        $producto->categoria_id = $datos['categoria_id'];
        $producto->proveedor_id = $datos['proveedor_id'];
        $producto->precio_compra = $datos['precio_compra'];
        $producto->precio_venta = $datos['precio_venta'];
        $producto->stock_minimo = $datos['stock_minimo'];
        $producto->stock_maximo = $datos['stock_maximo'];
        $producto->unidad_medida = $datos['unidad_medida'];
        $producto->peso = $datos['peso'];
        $producto->dimensiones = $datos['dimensiones'];
        $producto->activo = $datos['activo'];

        if ($producto->save()) {
            $this->flashSession->success('Producto actualizado exitosamente');
            $this->registrarActividad('update_product', "Producto actualizado: {$producto->nombre}", ['producto_id' => $producto->id]);
            return $this->response->redirect('/admin/productos');
        } else {
            $this->flashSession->error('Error al actualizar el producto');
            $errores = [];
            foreach ($producto->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('producto', $producto);
            
            $categorias = Categoria::obtenerActivas();
            $proveedores = Proveedor::obtenerActivos();
            $this->view->setVar('categorias', $categorias);
            $this->view->setVar('proveedores', $proveedores);
            
            return $this->dispatcher->forward([
                'action' => 'editar'
            ]);
        }
    }

    /**
     * Eliminar producto (desactivar)
     */
    public function eliminarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isDelete() && !$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $id = $this->dispatcher->getParam('id');
        
        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$producto) {
            return $this->jsonError('Producto no encontrado', 404);
        }

        // Verificar si el producto tiene pedidos asociados
        $tienePedidos = $producto->countDetallesPedido() > 0;
        
        if ($tienePedidos) {
            // Solo desactivar si tiene pedidos
            $producto->activo = false;
            if ($producto->save()) {
                $this->registrarActividad('deactivate_product', "Producto desactivado: {$producto->nombre}");
                return $this->jsonSuccess('Producto desactivado exitosamente');
            } else {
                return $this->jsonError('Error al desactivar el producto');
            }
        } else {
            // Eliminar completamente si no tiene pedidos
            if ($producto->delete()) {
                $this->registrarActividad('delete_product', "Producto eliminado: {$producto->nombre}");
                return $this->jsonSuccess('Producto eliminado exitosamente');
            } else {
                return $this->jsonError('Error al eliminar el producto');
            }
        }
    }

    /**
     * Ver detalle del producto
     */
    public function verAction()
    {
        $id = $this->dispatcher->getParam('id');
        $producto = Producto::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$producto) {
            $this->flashSession->error('Producto no encontrado');
            return $this->response->redirect('/admin/productos');
        }

        $this->view->setVar('title', 'Detalle del Producto: ' . $producto->nombre);
        $this->view->setVar('producto', $producto);
        
        // Obtener movimientos de inventario recientes
        $movimientos = \App\Models\InventarioMovimiento::obtenerPorProducto($id, 20);
        $this->view->setVar('movimientos', $movimientos);
        
        // Obtener imágenes del producto
        $imagenes = ProductoImagen::obtenerPorProducto($id);
        $this->view->setVar('imagenes', $imagenes);
    }

    /**
     * Valida los datos de un producto
     */
    private function validarProducto($datos, $requiereSku = true, $productoId = null)
    {
        $errores = [];

        // Validar SKU
        if ($requiereSku) {
            if (empty($datos['sku'])) {
                $errores['sku'] = 'El SKU es requerido';
            } else {
                // Verificar si el SKU ya existe (excluyendo el producto actual en edición)
                $conditions = 'sku = ?';
                $bind = [$datos['sku']];
                
                if ($productoId) {
                    $conditions .= ' AND id != ?';
                    $bind[] = $productoId;
                }
                
                $productoExistente = Producto::findFirst([
                    'conditions' => $conditions,
                    'bind' => $bind
                ]);
                
                if ($productoExistente) {
                    $errores['sku'] = 'Ya existe un producto con este SKU';
                }
            }
        }

        // Validar nombre
        if (empty($datos['nombre'])) {
            $errores['nombre'] = 'El nombre del producto es requerido';
        }

        // Validar categoría
        if (empty($datos['categoria_id']) || $datos['categoria_id'] <= 0) {
            $errores['categoria_id'] = 'Debe seleccionar una categoría válida';
        }

        // Validar proveedor
        if (empty($datos['proveedor_id']) || $datos['proveedor_id'] <= 0) {
            $errores['proveedor_id'] = 'Debe seleccionar un proveedor válido';
        }

        // Validar precios
        if (empty($datos['precio_compra']) || $datos['precio_compra'] <= 0) {
            $errores['precio_compra'] = 'El precio de compra debe ser mayor a 0';
        }

        if (empty($datos['precio_venta']) || $datos['precio_venta'] <= 0) {
            $errores['precio_venta'] = 'El precio de venta debe ser mayor a 0';
        }

        // Validar que el precio de venta sea mayor al de compra
        if (!empty($datos['precio_compra']) && !empty($datos['precio_venta']) && 
            $datos['precio_venta'] <= $datos['precio_compra']) {
            $errores['precio_venta'] = 'El precio de venta debe ser mayor al precio de compra';
        }

        // Validar stocks
        if (isset($datos['stock_minimo']) && $datos['stock_minimo'] < 0) {
            $errores['stock_minimo'] = 'El stock mínimo no puede ser negativo';
        }

        if (isset($datos['stock_maximo']) && $datos['stock_maximo'] < 0) {
            $errores['stock_maximo'] = 'El stock máximo no puede ser negativo';
        }

        if (isset($datos['stock_minimo']) && isset($datos['stock_maximo']) && 
            $datos['stock_maximo'] < $datos['stock_minimo']) {
            $errores['stock_maximo'] = 'El stock máximo debe ser mayor al stock mínimo';
        }

        return $errores;
    }
}

