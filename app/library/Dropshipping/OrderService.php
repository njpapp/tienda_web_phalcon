<?php

namespace App\Library\Dropshipping;

use App\Models\PedidoDropshipping;
use App\Models\ProveedorDropshipping;
use App\Models\ProductoExterno;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\AlertaSistema;

/**
 * Servicio para el manejo de pedidos de dropshipping
 */
class OrderService
{
    private $logger;
    private $emailService;

    public function __construct()
    {
        $this->logger = \Phalcon\Di::getDefault()->getLogger();
        $this->emailService = \Phalcon\Di::getDefault()->getShared('emailService');
    }

    /**
     * Procesa un pedido para dropshipping
     */
    public function procesarPedidoDropshipping(Pedido $pedido)
    {
        $this->logger->info("Procesando pedido dropshipping #{$pedido->id}");
        
        $detalles = $pedido->getDetalles();
        $pedidosCreados = [];
        $errores = [];

        foreach ($detalles as $detalle) {
            try {
                // Verificar si el producto es de dropshipping
                $productoExterno = ProductoExterno::findFirst([
                    'conditions' => 'producto_id_interno = ? AND disponible = true',
                    'bind' => [$detalle->producto_id],
                    'order' => 'precio_proveedor ASC' // Elegir el más barato si hay múltiples
                ]);

                if ($productoExterno) {
                    $pedidoDropshipping = $this->crearPedidoEnProveedor($productoExterno, $detalle, $pedido);
                    if ($pedidoDropshipping) {
                        $pedidosCreados[] = $pedidoDropshipping;
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error("Error procesando detalle del pedido {$pedido->id}: " . $e->getMessage());
                $errores[] = [
                    'producto_id' => $detalle->producto_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Actualizar estado del pedido principal
        if (!empty($pedidosCreados)) {
            $pedido->estado = 'procesando';
            $pedido->save();
            
            // Enviar confirmación al cliente
            $this->enviarConfirmacionPedido($pedido, $pedidosCreados);
        }

        // Crear alertas para errores
        foreach ($errores as $error) {
            $this->crearAlerta('order_error', null, 
                'Error procesando pedido', 
                "Error en pedido #{$pedido->id}, producto {$error['producto_id']}: {$error['error']}",
                'error'
            );
        }

        return [
            'pedidos_creados' => count($pedidosCreados),
            'errores' => count($errores),
            'detalles_errores' => $errores
        ];
    }

    /**
     * Crea un pedido en el proveedor externo
     */
    private function crearPedidoEnProveedor(ProductoExterno $productoExterno, PedidoDetalle $detalle, Pedido $pedido)
    {
        $proveedor = $productoExterno->Proveedor;
        
        if (!$proveedor || !$proveedor->activo) {
            throw new \Exception("Proveedor no disponible");
        }

        try {
            $adapter = $proveedor->getAdapter();
            
            // Crear pedido en el proveedor
            $pedidoExterno = $adapter->crearPedido($productoExterno, $detalle, $pedido);
            
            // Guardar información del pedido de dropshipping
            $pedidoDropshipping = new PedidoDropshipping();
            $pedidoDropshipping->pedido_id = $pedido->id;
            $pedidoDropshipping->proveedor_id = $proveedor->id;
            $pedidoDropshipping->producto_externo_id = $productoExterno->id;
            $pedidoDropshipping->pedido_id_externo = $pedidoExterno['order_id'];
            $pedidoDropshipping->estado_externo = $pedidoExterno['status'] ?? 'pending';
            $pedidoDropshipping->fecha_pedido_externo = date('Y-m-d H:i:s');
            $pedidoDropshipping->costo_producto = $productoExterno->precio_proveedor * $detalle->cantidad;
            $pedidoDropshipping->costo_envio = $pedidoExterno['shipping_cost'] ?? 0;
            $pedidoDropshipping->costo_total = $pedidoDropshipping->costo_producto + $pedidoDropshipping->costo_envio;
            
            if (isset($pedidoExterno['estimated_delivery'])) {
                $pedidoDropshipping->fecha_entrega_estimada = $pedidoExterno['estimated_delivery'];
            }
            
            if ($pedidoDropshipping->save()) {
                $this->logger->info("Pedido dropshipping creado: {$pedidoDropshipping->id}");
                return $pedidoDropshipping;
            } else {
                throw new \Exception("Error guardando pedido dropshipping: " . implode(', ', $pedidoDropshipping->getMessages()));
            }

        } catch (\Exception $e) {
            $this->logger->error("Error creando pedido en proveedor {$proveedor->nombre}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza el estado de todos los pedidos de dropshipping
     */
    public function actualizarEstadosPedidos()
    {
        $this->logger->info("Iniciando actualización de estados de pedidos");
        
        // Obtener pedidos que no están completados
        $pedidosActivos = PedidoDropshipping::find([
            'conditions' => 'estado_externo NOT IN (?, ?, ?)',
            'bind' => ['delivered', 'cancelled', 'returned']
        ]);

        $actualizados = 0;
        $errores = 0;

        foreach ($pedidosActivos as $pedidoDropshipping) {
            try {
                $this->actualizarEstadoPedido($pedidoDropshipping);
                $actualizados++;
                
                // Pausa para evitar sobrecarga de APIs
                usleep(500000); // 0.5 segundos
                
            } catch (\Exception $e) {
                $this->logger->error("Error actualizando pedido {$pedidoDropshipping->id}: " . $e->getMessage());
                $errores++;
            }
        }

        $this->logger->info("Actualización completada: {$actualizados} actualizados, {$errores} errores");
        
        return [
            'total_procesados' => count($pedidosActivos),
            'actualizados' => $actualizados,
            'errores' => $errores
        ];
    }

    /**
     * Actualiza el estado de un pedido específico
     */
    public function actualizarEstadoPedido(PedidoDropshipping $pedidoDropshipping)
    {
        $proveedor = $pedidoDropshipping->Proveedor;
        
        if (!$proveedor || !$proveedor->activo) {
            return false;
        }

        try {
            $adapter = $proveedor->getAdapter();
            $estadoActual = $adapter->obtenerEstadoPedido($pedidoDropshipping->pedido_id_externo);
            
            if ($estadoActual && $estadoActual['status'] !== $pedidoDropshipping->estado_externo) {
                // Actualizar información del pedido
                $datosActualizacion = [];
                
                if (isset($estadoActual['tracking_number'])) {
                    $pedidoDropshipping->tracking_number = $estadoActual['tracking_number'];
                    $datosActualizacion['tracking_number'] = $estadoActual['tracking_number'];
                }
                
                if (isset($estadoActual['carrier'])) {
                    $pedidoDropshipping->carrier = $estadoActual['carrier'];
                    $datosActualizacion['carrier'] = $estadoActual['carrier'];
                }
                
                if (isset($estadoActual['estimated_delivery'])) {
                    $pedidoDropshipping->fecha_entrega_estimada = $estadoActual['estimated_delivery'];
                }
                
                // Actualizar estado
                $pedidoDropshipping->actualizarEstado($estadoActual['status'], $datosActualizacion);
                
                $this->logger->info("Estado actualizado para pedido {$pedidoDropshipping->id}: {$estadoActual['status']}");
                
                // Obtener información de seguimiento si está disponible
                if ($pedidoDropshipping->tracking_number) {
                    $this->actualizarSeguimiento($pedidoDropshipping);
                }
                
                return true;
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Error actualizando estado del pedido {$pedidoDropshipping->id}: " . $e->getMessage());
            throw $e;
        }
        
        return false;
    }

    /**
     * Actualiza la información de seguimiento de un pedido
     */
    private function actualizarSeguimiento(PedidoDropshipping $pedidoDropshipping)
    {
        if (!$pedidoDropshipping->tracking_number) {
            return;
        }

        try {
            $proveedor = $pedidoDropshipping->Proveedor;
            $adapter = $proveedor->getAdapter();
            
            $seguimiento = $adapter->obtenerSeguimiento($pedidoDropshipping->tracking_number);
            
            if ($seguimiento) {
                $pedidoDropshipping->datos_seguimiento = $seguimiento;
                $pedidoDropshipping->save();
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Error actualizando seguimiento para pedido {$pedidoDropshipping->id}: " . $e->getMessage());
        }
    }

    /**
     * Envía confirmación de pedido al cliente
     */
    private function enviarConfirmacionPedido(Pedido $pedido, $pedidosDropshipping)
    {
        try {
            $cliente = $pedido->Usuario;
            
            $asunto = "Confirmación de Pedido #{$pedido->id}";
            $mensaje = $this->generarMensajeConfirmacion($pedido, $pedidosDropshipping);
            
            $this->emailService->enviarEmail($cliente->email, $asunto, $mensaje);
            
            $this->logger->info("Confirmación enviada para pedido {$pedido->id}");
            
        } catch (\Exception $e) {
            $this->logger->error("Error enviando confirmación de pedido {$pedido->id}: " . $e->getMessage());
        }
    }

    /**
     * Genera el mensaje de confirmación de pedido
     */
    private function generarMensajeConfirmacion(Pedido $pedido, $pedidosDropshipping)
    {
        $mensaje = "Estimado/a {$pedido->Usuario->getNombreCompleto()},\n\n";
        $mensaje .= "Su pedido #{$pedido->id} ha sido confirmado y está siendo procesado.\n\n";
        $mensaje .= "Detalles del pedido:\n";
        $mensaje .= "Fecha: " . date('d/m/Y H:i', strtotime($pedido->created_at)) . "\n";
        $mensaje .= "Total: $" . number_format($pedido->total, 2) . "\n\n";
        
        $mensaje .= "Sus productos serán enviados desde diferentes proveedores:\n\n";
        
        foreach ($pedidosDropshipping as $pd) {
            $producto = $pd->ProductoExterno;
            $proveedor = $pd->Proveedor;
            
            $mensaje .= "- {$producto->titulo}\n";
            $mensaje .= "  Proveedor: {$proveedor->nombre}\n";
            $mensaje .= "  Tiempo estimado de entrega: {$producto->tiempo_envio_min}-{$producto->tiempo_envio_max} días\n\n";
        }
        
        $mensaje .= "Recibirá notificaciones por email cuando sus productos sean enviados.\n\n";
        $mensaje .= "Gracias por su compra.\n\n";
        $mensaje .= "Equipo de Atención al Cliente";
        
        return $mensaje;
    }

    /**
     * Envía notificaciones de seguimiento a clientes
     */
    public function enviarNotificacionesSeguimiento()
    {
        // Obtener pedidos enviados sin notificación de seguimiento
        $pedidosEnviados = PedidoDropshipping::find([
            'conditions' => 'estado_externo = ? AND tracking_number IS NOT NULL AND notas NOT LIKE ?',
            'bind' => ['shipped', '%tracking_sent%']
        ]);

        $enviados = 0;

        foreach ($pedidosEnviados as $pedidoDropshipping) {
            try {
                $this->enviarNotificacionSeguimiento($pedidoDropshipping);
                
                // Marcar como enviado
                $pedidoDropshipping->notas = ($pedidoDropshipping->notas ?? '') . ' tracking_sent';
                $pedidoDropshipping->save();
                
                $enviados++;
                
            } catch (\Exception $e) {
                $this->logger->error("Error enviando notificación de seguimiento para pedido {$pedidoDropshipping->id}: " . $e->getMessage());
            }
        }

        return $enviados;
    }

    /**
     * Envía notificación de seguimiento individual
     */
    private function enviarNotificacionSeguimiento(PedidoDropshipping $pedidoDropshipping)
    {
        $pedido = $pedidoDropshipping->Pedido;
        $cliente = $pedido->Usuario;
        $producto = $pedidoDropshipping->ProductoExterno;
        
        $asunto = "Su pedido ha sido enviado - Tracking #{$pedidoDropshipping->tracking_number}";
        
        $mensaje = "Estimado/a {$cliente->getNombreCompleto()},\n\n";
        $mensaje .= "Su producto '{$producto->titulo}' del pedido #{$pedido->id} ha sido enviado.\n\n";
        $mensaje .= "Información de envío:\n";
        $mensaje .= "Número de seguimiento: {$pedidoDropshipping->tracking_number}\n";
        
        if ($pedidoDropshipping->carrier) {
            $mensaje .= "Transportista: {$pedidoDropshipping->carrier}\n";
        }
        
        if ($pedidoDropshipping->fecha_entrega_estimada) {
            $mensaje .= "Fecha estimada de entrega: " . date('d/m/Y', strtotime($pedidoDropshipping->fecha_entrega_estimada)) . "\n";
        }
        
        $urlSeguimiento = $pedidoDropshipping->getUrlSeguimiento();
        if ($urlSeguimiento) {
            $mensaje .= "\nPuede rastrear su envío en: {$urlSeguimiento}\n";
        }
        
        $mensaje .= "\nGracias por su compra.\n\n";
        $mensaje .= "Equipo de Atención al Cliente";
        
        $this->emailService->enviarEmail($cliente->email, $asunto, $mensaje);
    }

    /**
     * Detecta y notifica pedidos retrasados
     */
    public function detectarPedidosRetrasados()
    {
        $pedidosRetrasados = PedidoDropshipping::getRetrasados();
        $notificados = 0;

        foreach ($pedidosRetrasados as $pedidoDropshipping) {
            // Verificar si ya se notificó el retraso
            if (strpos($pedidoDropshipping->notas ?? '', 'delay_notified') !== false) {
                continue;
            }

            try {
                $this->notificarRetraso($pedidoDropshipping);
                
                // Marcar como notificado
                $pedidoDropshipping->notas = ($pedidoDropshipping->notas ?? '') . ' delay_notified';
                $pedidoDropshipping->save();
                
                $notificados++;
                
            } catch (\Exception $e) {
                $this->logger->error("Error notificando retraso para pedido {$pedidoDropshipping->id}: " . $e->getMessage());
            }
        }

        return $notificados;
    }

    /**
     * Notifica retraso en la entrega
     */
    private function notificarRetraso(PedidoDropshipping $pedidoDropshipping)
    {
        $pedido = $pedidoDropshipping->Pedido;
        $cliente = $pedido->Usuario;
        $producto = $pedidoDropshipping->ProductoExterno;
        
        $diasRetraso = $pedidoDropshipping->estaRetrasado() ? 
            (new \DateTime())->diff(new \DateTime($pedidoDropshipping->fecha_entrega_estimada))->days : 0;
        
        $asunto = "Actualización sobre su pedido #{$pedido->id}";
        
        $mensaje = "Estimado/a {$cliente->getNombreCompleto()},\n\n";
        $mensaje .= "Queremos informarle sobre el estado de su pedido #{$pedido->id}.\n\n";
        $mensaje .= "El producto '{$producto->titulo}' está experimentando un retraso en la entrega.\n";
        $mensaje .= "Fecha original estimada: " . date('d/m/Y', strtotime($pedidoDropshipping->fecha_entrega_estimada)) . "\n";
        $mensaje .= "Retraso aproximado: {$diasRetraso} días\n\n";
        
        if ($pedidoDropshipping->tracking_number) {
            $mensaje .= "Número de seguimiento: {$pedidoDropshipping->tracking_number}\n";
        }
        
        $mensaje .= "\nNos disculpamos por las molestias y estamos trabajando para resolver esta situación.\n";
        $mensaje .= "Le mantendremos informado sobre cualquier actualización.\n\n";
        $mensaje .= "Gracias por su paciencia.\n\n";
        $mensaje .= "Equipo de Atención al Cliente";
        
        $this->emailService->enviarEmail($cliente->email, $asunto, $mensaje);
        
        // Crear alerta interna
        $this->crearAlerta('delivery_delay', $pedidoDropshipping->proveedor_id,
            'Pedido retrasado',
            "Pedido #{$pedido->id} retrasado {$diasRetraso} días",
            'warning'
        );
    }

    /**
     * Crea una alerta del sistema
     */
    private function crearAlerta($tipo, $proveedorId, $titulo, $mensaje, $nivel = 'info')
    {
        $alerta = new AlertaSistema();
        $alerta->tipo = $tipo;
        $alerta->proveedor_id = $proveedorId;
        $alerta->titulo = $titulo;
        $alerta->mensaje = $mensaje;
        $alerta->nivel = $nivel;
        $alerta->save();
    }

    /**
     * Obtiene estadísticas de pedidos de dropshipping
     */
    public function getEstadisticasPedidos($dias = 30)
    {
        return PedidoDropshipping::getEstadisticas($dias);
    }
}

