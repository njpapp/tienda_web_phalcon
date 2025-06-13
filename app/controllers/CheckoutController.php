<?php

namespace App\Controllers;

use App\Models\Carrito;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\TarjetaCliente;
use App\Models\EstadoPedido;

class CheckoutController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Verificar que el usuario es cliente
        if (!$this->requireCliente()) {
            return false;
        }
    }

    /**
     * Página de checkout
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Finalizar Compra');
        
        // Verificar que hay items en el carrito
        $items = Carrito::obtenerPorCliente($this->usuario->id);
        
        if (count($items) == 0) {
            $this->flashSession->error('Tu carrito está vacío');
            return $this->response->redirect('/carrito');
        }
        
        // Verificar disponibilidad de productos
        $errores = $this->verificarDisponibilidadProductos($items);
        if (!empty($errores)) {
            foreach ($errores as $error) {
                $this->flashSession->error($error);
            }
            return $this->response->redirect('/carrito');
        }
        
        $this->view->setVar('items', $items);
        
        // Calcular totales
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->getSubtotal();
        }
        
        $impuestos = $subtotal * 0.19; // 19% IVA Colombia
        $total = $subtotal + $impuestos;
        
        $this->view->setVar('subtotal', $subtotal);
        $this->view->setVar('impuestos', $impuestos);
        $this->view->setVar('total', $total);
        
        // Obtener tarjetas del cliente
        $tarjetas = TarjetaCliente::obtenerPorCliente($this->usuario->id);
        $this->view->setVar('tarjetas', $tarjetas);
        
        // Datos del cliente para envío
        $this->view->setVar('cliente', $this->usuario);
    }

    /**
     * Procesar la compra
     */
    public function procesarAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/checkout');
        }

        // Verificar que hay items en el carrito
        $items = Carrito::obtenerPorCliente($this->usuario->id);
        
        if (count($items) == 0) {
            $this->flashSession->error('Tu carrito está vacío');
            return $this->response->redirect('/carrito');
        }

        // Verificar disponibilidad de productos
        $errores = $this->verificarDisponibilidadProductos($items);
        if (!empty($errores)) {
            foreach ($errores as $error) {
                $this->flashSession->error($error);
            }
            return $this->response->redirect('/carrito');
        }

        // Obtener datos del formulario
        $datos = [
            'metodo_pago' => $this->request->getPost('metodo_pago', 'string'),
            'tarjeta_id' => $this->request->getPost('tarjeta_id', 'int'),
            'direccion_envio' => $this->request->getPost('direccion_envio', 'string'),
            'notas' => $this->request->getPost('notas', 'string')
        ];

        // Validar datos
        $erroresValidacion = $this->validarDatosCheckout($datos);
        if (!empty($erroresValidacion)) {
            foreach ($erroresValidacion as $error) {
                $this->flashSession->error($error);
            }
            return $this->response->redirect('/checkout');
        }

        // Iniciar transacción
        $this->db->begin();

        try {
            // Crear el pedido
            $pedido = $this->crearPedido($items, $datos);
            
            if (!$pedido) {
                throw new \Exception('Error al crear el pedido');
            }

            // Crear los detalles del pedido y actualizar stock
            $this->crearDetallesPedido($pedido, $items);

            // Vaciar el carrito
            Carrito::vaciar($this->usuario->id);

            // Confirmar transacción
            $this->db->commit();

            $this->flashSession->success('¡Compra realizada exitosamente! Tu pedido es: ' . $pedido->numero_pedido);
            $this->registrarActividad('purchase_completed', "Compra completada: {$pedido->numero_pedido}", [
                'pedido_id' => $pedido->id,
                'total' => $pedido->total
            ]);

            return $this->response->redirect('/cliente/compras/' . $pedido->id);

        } catch (\Exception $e) {
            // Revertir transacción
            $this->db->rollback();
            
            $this->logger->error('Error en checkout: ' . $e->getMessage());
            $this->flashSession->error('Error al procesar la compra. Por favor intenta nuevamente.');
            
            return $this->response->redirect('/checkout');
        }
    }

    /**
     * Confirmar pedido (página de éxito)
     */
    public function exitoAction()
    {
        $pedidoId = $this->dispatcher->getParam('id');
        
        $pedido = Pedido::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$pedidoId, $this->usuario->id]
        ]);

        if (!$pedido) {
            $this->flashSession->error('Pedido no encontrado');
            return $this->response->redirect('/cliente/compras');
        }

        $this->view->setVar('title', 'Compra Exitosa');
        $this->view->setVar('pedido', $pedido);
    }

    /**
     * Verifica la disponibilidad de productos en el carrito
     */
    private function verificarDisponibilidadProductos($items)
    {
        $errores = [];
        
        foreach ($items as $item) {
            if (!$item->producto->activo) {
                $errores[] = "El producto '{$item->producto->nombre}' ya no está disponible";
            } elseif (!$item->producto->tieneStock($item->cantidad)) {
                $errores[] = "Stock insuficiente para '{$item->producto->nombre}'. Disponible: {$item->producto->stock_actual}";
            }
        }
        
        return $errores;
    }

    /**
     * Valida los datos del checkout
     */
    private function validarDatosCheckout($datos)
    {
        $errores = [];

        if (empty($datos['metodo_pago'])) {
            $errores[] = 'Debe seleccionar un método de pago';
        } elseif (!in_array($datos['metodo_pago'], ['efectivo', 'tarjeta', 'transferencia'])) {
            $errores[] = 'Método de pago inválido';
        }

        if ($datos['metodo_pago'] === 'tarjeta') {
            if (empty($datos['tarjeta_id'])) {
                $errores[] = 'Debe seleccionar una tarjeta';
            } else {
                // Verificar que la tarjeta pertenece al cliente
                $tarjeta = TarjetaCliente::findFirst([
                    'conditions' => 'id = ? AND cliente_id = ? AND activo = true',
                    'bind' => [$datos['tarjeta_id'], $this->usuario->id]
                ]);
                
                if (!$tarjeta) {
                    $errores[] = 'Tarjeta no válida';
                } elseif ($tarjeta->haExpirado()) {
                    $errores[] = 'La tarjeta seleccionada ha expirado';
                }
            }
        }

        if (empty($datos['direccion_envio'])) {
            $errores[] = 'La dirección de envío es requerida';
        }

        return $errores;
    }

    /**
     * Crea el pedido principal
     */
    private function crearPedido($items, $datos)
    {
        // Calcular totales
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->getSubtotal();
        }
        
        $impuestos = $subtotal * 0.19; // 19% IVA Colombia
        $total = $subtotal + $impuestos;

        // Crear pedido
        $pedido = new Pedido();
        $pedido->cliente_id = $this->usuario->id;
        $pedido->subtotal = $subtotal;
        $pedido->impuestos = $impuestos;
        $pedido->total = $total;
        $pedido->metodo_pago = $datos['metodo_pago'];
        $pedido->direccion_envio = $datos['direccion_envio'];
        $pedido->notas = $datos['notas'];

        // Obtener estado pendiente
        $estadoPendiente = EstadoPedido::obtenerPorNombre('pendiente');
        $pedido->estado_id = $estadoPendiente ? $estadoPendiente->id : 1;

        if ($pedido->save()) {
            return $pedido;
        }

        return false;
    }

    /**
     * Crea los detalles del pedido y actualiza el stock
     */
    private function crearDetallesPedido($pedido, $items)
    {
        foreach ($items as $item) {
            // Crear detalle del pedido
            $detalle = new PedidoDetalle();
            $detalle->pedido_id = $pedido->id;
            $detalle->producto_id = $item->producto_id;
            $detalle->cantidad = $item->cantidad;
            $detalle->precio_unitario = $item->precio_unitario;
            $detalle->subtotal = $item->getSubtotal();

            if (!$detalle->save()) {
                throw new \Exception('Error al crear detalle del pedido para producto: ' . $item->producto->nombre);
            }

            // Reducir stock del producto
            if (!$item->producto->reducirStock(
                $item->cantidad, 
                'Venta', 
                $pedido->numero_pedido, 
                $this->usuario->id
            )) {
                throw new \Exception('Error al actualizar stock del producto: ' . $item->producto->nombre);
            }
        }
    }

    /**
     * Calcular costo de envío (placeholder)
     */
    private function calcularCostoEnvio($direccion)
    {
        // Por ahora envío gratis
        return 0;
    }

    /**
     * Procesar pago (placeholder para integración con pasarela de pagos)
     */
    private function procesarPago($pedido, $metodoPago, $tarjetaId = null)
    {
        // Aquí se integraría con una pasarela de pagos real
        // Por ahora simulamos que el pago es exitoso
        
        switch ($metodoPago) {
            case 'tarjeta':
                // Simular procesamiento con tarjeta
                $this->logger->info("Pago con tarjeta procesado para pedido {$pedido->numero_pedido}");
                break;
                
            case 'transferencia':
                // Simular transferencia bancaria
                $this->logger->info("Transferencia bancaria registrada para pedido {$pedido->numero_pedido}");
                break;
                
            case 'efectivo':
                // Pago en efectivo contra entrega
                $this->logger->info("Pago en efectivo registrado para pedido {$pedido->numero_pedido}");
                break;
        }
        
        return true; // Simular éxito
    }
}

