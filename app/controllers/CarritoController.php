<?php

namespace App\Controllers;

use App\Models\Carrito;
use App\Models\Producto;

class CarritoController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Verificar que el usuario es cliente para operaciones del carrito
        if (!$this->auth->esCliente()) {
            $this->flashSession->error('Debes iniciar sesión como cliente para usar el carrito');
            return $this->response->redirect('/login');
        }
    }

    /**
     * Ver contenido del carrito
     */
    public function verAction()
    {
        $this->view->setVar('title', 'Mi Carrito');
        
        // Obtener items del carrito
        $items = Carrito::obtenerPorCliente($this->usuario->id);
        $this->view->setVar('items', $items);
        
        // Calcular totales
        $subtotal = 0;
        $totalItems = 0;
        
        foreach ($items as $item) {
            $subtotal += $item->getSubtotal();
            $totalItems += $item->cantidad;
        }
        
        // Calcular impuestos (19% IVA Colombia)
        $impuestos = $subtotal * 0.16;
        $total = $subtotal + $impuestos;
        
        $this->view->setVar('subtotal', $subtotal);
        $this->view->setVar('impuestos', $impuestos);
        $this->view->setVar('total', $total);
        $this->view->setVar('totalItems', $totalItems);
        
        $this->registrarActividad('view_cart', 'Cliente vio su carrito');
    }

    /**
     * Agregar producto al carrito
     */
    public function agregarAction()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $productoId = $this->request->getPost('producto_id', 'int');
        $cantidad = $this->request->getPost('cantidad', 'int', 1);

        // Validar datos
        if (!$productoId || $cantidad <= 0) {
            return $this->jsonError('Datos inválidos');
        }

        // Verificar que el producto existe y está activo
        $producto = Producto::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$productoId]
        ]);

        if (!$producto) {
            return $this->jsonError('Producto no encontrado');
        }

        // Verificar stock disponible
        if (!$producto->tieneStock($cantidad)) {
            return $this->jsonError('Stock insuficiente. Disponible: ' . $producto->stock_actual);
        }

        // Verificar si ya existe en el carrito
        $itemExistente = Carrito::findFirst([
            'conditions' => 'cliente_id = ? AND producto_id = ?',
            'bind' => [$this->usuario->id, $productoId]
        ]);

        if ($itemExistente) {
            // Verificar stock para la nueva cantidad total
            $nuevaCantidad = $itemExistente->cantidad + $cantidad;
            if (!$producto->tieneStock($nuevaCantidad)) {
                return $this->jsonError('Stock insuficiente para la cantidad solicitada');
            }
            
            $itemExistente->cantidad = $nuevaCantidad;
            $itemExistente->precio_unitario = $producto->precio_venta;
            
            if ($itemExistente->save()) {
                $this->registrarActividad('update_cart_item', "Producto {$producto->nombre} actualizado en carrito");
                return $this->jsonSuccess('Producto actualizado en el carrito', [
                    'cantidad' => $itemExistente->cantidad,
                    'subtotal' => $itemExistente->getSubtotalFormateado()
                ]);
            } else {
                return $this->jsonError('Error al actualizar el producto en el carrito');
            }
        } else {
            // Crear nuevo item en el carrito
            $item = new Carrito();
            $item->cliente_id = $this->usuario->id;
            $item->producto_id = $productoId;
            $item->cantidad = $cantidad;
            $item->precio_unitario = $producto->precio_venta;
            
            if ($item->save()) {
                $this->registrarActividad('add_to_cart', "Producto {$producto->nombre} agregado al carrito");
                return $this->jsonSuccess('Producto agregado al carrito', [
                    'cantidad' => $item->cantidad,
                    'subtotal' => $item->getSubtotalFormateado()
                ]);
            } else {
                return $this->jsonError('Error al agregar el producto al carrito');
            }
        }
    }

    /**
     * Actualizar cantidad de un item del carrito
     */
    public function actualizarAction()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $itemId = $this->request->getPost('item_id', 'int');
        $cantidad = $this->request->getPost('cantidad', 'int');

        // Validar datos
        if (!$itemId || $cantidad < 0) {
            return $this->jsonError('Datos inválidos');
        }

        // Buscar el item del carrito
        $item = Carrito::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$itemId, $this->usuario->id]
        ]);

        if (!$item) {
            return $this->jsonError('Item no encontrado en el carrito');
        }

        // Si la cantidad es 0, eliminar el item
        if ($cantidad == 0) {
            if ($item->delete()) {
                $this->registrarActividad('remove_from_cart', "Producto eliminado del carrito");
                return $this->jsonSuccess('Producto eliminado del carrito');
            } else {
                return $this->jsonError('Error al eliminar el producto del carrito');
            }
        }

        // Verificar stock disponible
        if (!$item->producto->tieneStock($cantidad)) {
            return $this->jsonError('Stock insuficiente. Disponible: ' . $item->producto->stock_actual);
        }

        // Actualizar cantidad
        $item->cantidad = $cantidad;
        $item->precio_unitario = $item->producto->precio_venta; // Actualizar precio por si cambió

        if ($item->save()) {
            $this->registrarActividad('update_cart_item', "Cantidad actualizada en carrito");
            
            // Calcular nuevos totales del carrito
            $totalCarrito = Carrito::calcularTotal($this->usuario->id);
            
            return $this->jsonSuccess('Cantidad actualizada', [
                'cantidad' => $item->cantidad,
                'subtotal' => $item->getSubtotalFormateado(),
                'total_carrito' => '$' . number_format($totalCarrito, 2)
            ]);
        } else {
            return $this->jsonError('Error al actualizar la cantidad');
        }
    }

    /**
     * Eliminar item del carrito
     */
    public function eliminarAction()
    {
        if (!$this->request->isDelete() && !$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $itemId = $this->dispatcher->getParam('id');

        // Buscar el item del carrito
        $item = Carrito::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$itemId, $this->usuario->id]
        ]);

        if (!$item) {
            return $this->jsonError('Item no encontrado en el carrito');
        }

        if ($item->delete()) {
            $this->registrarActividad('remove_from_cart', "Producto eliminado del carrito");
            
            // Calcular nuevo total del carrito
            $totalCarrito = Carrito::calcularTotal($this->usuario->id);
            
            return $this->jsonSuccess('Producto eliminado del carrito', [
                'total_carrito' => '$' . number_format($totalCarrito, 2)
            ]);
        } else {
            return $this->jsonError('Error al eliminar el producto del carrito');
        }
    }

    /**
     * Vaciar todo el carrito
     */
    public function vaciarAction()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        if (Carrito::vaciar($this->usuario->id)) {
            $this->registrarActividad('clear_cart', "Carrito vaciado");
            return $this->jsonSuccess('Carrito vaciado exitosamente');
        } else {
            return $this->jsonError('Error al vaciar el carrito');
        }
    }

    /**
     * Obtener resumen del carrito (para AJAX)
     */
    public function resumenAction()
    {
        $items = Carrito::obtenerPorCliente($this->usuario->id);
        $total = Carrito::calcularTotal($this->usuario->id);
        $totalItems = 0;
        
        foreach ($items as $item) {
            $totalItems += $item->cantidad;
        }
        
        return $this->jsonSuccess('Resumen del carrito', [
            'total_items' => $totalItems,
            'total' => '$' . number_format($total, 2),
            'items' => count($items)
        ]);
    }

    /**
     * Verificar disponibilidad de productos en el carrito
     */
    public function verificarDisponibilidadAction()
    {
        $items = Carrito::obtenerPorCliente($this->usuario->id);
        $errores = [];
        
        foreach ($items as $item) {
            if (!$item->producto->activo) {
                $errores[] = "El producto '{$item->producto->nombre}' ya no está disponible";
            } elseif (!$item->producto->tieneStock($item->cantidad)) {
                $errores[] = "Stock insuficiente para '{$item->producto->nombre}'. Disponible: {$item->producto->stock_actual}";
            }
        }
        
        if (!empty($errores)) {
            return $this->jsonError('Hay problemas con algunos productos en tu carrito', 400, $errores);
        }
        
        return $this->jsonSuccess('Todos los productos están disponibles');
    }
}

