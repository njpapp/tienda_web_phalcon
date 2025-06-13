<?php

namespace App\Controllers;

use App\Models\Producto;
use App\Models\Categoria;

class TiendaController extends BaseController
{
    /**
     * Página principal de la tienda
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Tienda Online');
        
        // Obtener categorías activas
        $categorias = Categoria::obtenerActivas();
        $this->view->setVar('categorias', $categorias);
        
        // Obtener productos destacados (últimos 12)
        $productosDestacados = Producto::find([
            'conditions' => 'activo = true',
            'order' => 'created_at DESC',
            'limit' => 12
        ]);
        $this->view->setVar('productosDestacados', $productosDestacados);
        
        // Obtener productos más vendidos (simulado por ahora)
        $productosMasVendidos = Producto::find([
            'conditions' => 'activo = true',
            'order' => 'id ASC',
            'limit' => 8
        ]);
        $this->view->setVar('productosMasVendidos', $productosMasVendidos);
        
        // Buscar término si se proporciona
        $busqueda = $this->request->getQuery('busqueda', 'string', '');
        if (!empty($busqueda)) {
            $productosEncontrados = Producto::buscar($busqueda, 20);
            $this->view->setVar('productosEncontrados', $productosEncontrados);
            $this->view->setVar('busqueda', $busqueda);
        }
    }

    /**
     * Productos por categoría
     */
    public function categoriaAction()
    {
        $id = $this->dispatcher->getParam('id');
        
        $categoria = Categoria::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$categoria) {
            $this->flashSession->error('Categoría no encontrada');
            return $this->response->redirect('/tienda');
        }

        $this->view->setVar('title', 'Categoría: ' . $categoria->nombre);
        $this->view->setVar('categoria', $categoria);
        
        // Obtener parámetros de paginación
        $paginacion = $this->obtenerPaginacion();
        
        // Obtener productos de la categoría
        $productos = Producto::find([
            'conditions' => 'categoria_id = ? AND activo = true',
            'bind' => [$id],
            'order' => 'nombre ASC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalProductos = Producto::count([
            'conditions' => 'categoria_id = ? AND activo = true',
            'bind' => [$id]
        ]);
        
        $this->view->setVar('productos', $productos);
        $this->view->setVar('totalProductos', $totalProductos);
        $this->view->setVar('paginacion', $paginacion);
        
        // Obtener todas las categorías para el menú
        $categorias = Categoria::obtenerActivas();
        $this->view->setVar('categorias', $categorias);
    }

    /**
     * Detalle de un producto
     */
    public function productoAction()
    {
        $id = $this->dispatcher->getParam('id');
        
        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$producto) {
            $this->flashSession->error('Producto no encontrado');
            return $this->response->redirect('/tienda');
        }

        $this->view->setVar('title', $producto->nombre);
        $this->view->setVar('producto', $producto);
        
        // Obtener productos relacionados de la misma categoría
        $productosRelacionados = Producto::find([
            'conditions' => 'categoria_id = ? AND id != ? AND activo = true',
            'bind' => [$producto->categoria_id, $id],
            'order' => 'RANDOM()',
            'limit' => 4
        ]);
        $this->view->setVar('productosRelacionados', $productosRelacionados);
        
        // Obtener todas las categorías para el menú
        $categorias = Categoria::obtenerActivas();
        $this->view->setVar('categorias', $categorias);
        
        // Verificar si el usuario está autenticado para mostrar botón de agregar al carrito
        $this->view->setVar('puedeComprar', $this->auth->esCliente());
    }

    /**
     * Búsqueda de productos
     */
    public function buscarAction()
    {
        $termino = $this->request->getQuery('q', 'string', '');
        
        if (empty($termino)) {
            return $this->response->redirect('/tienda');
        }

        $this->view->setVar('title', 'Búsqueda: ' . $termino);
        $this->view->setVar('termino', $termino);
        
        // Obtener parámetros de paginación
        $paginacion = $this->obtenerPaginacion();
        
        // Buscar productos
        $productos = Producto::find([
            'conditions' => 'activo = true AND (nombre ILIKE ?1 OR sku ILIKE ?1 OR descripcion ILIKE ?1)',
            'bind' => [1 => "%{$termino}%"],
            'order' => 'nombre ASC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalProductos = Producto::count([
            'conditions' => 'activo = true AND (nombre ILIKE ?1 OR sku ILIKE ?1 OR descripcion ILIKE ?1)',
            'bind' => [1 => "%{$termino}%"]
        ]);
        
        $this->view->setVar('productos', $productos);
        $this->view->setVar('totalProductos', $totalProductos);
        $this->view->setVar('paginacion', $paginacion);
        
        // Obtener todas las categorías para el menú
        $categorias = Categoria::obtenerActivas();
        $this->view->setVar('categorias', $categorias);
    }
}

