<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\InclusionIn;

class PedidoDropshipping extends Model
{
    public $id;
    public $pedido_id;
    public $proveedor_id;
    public $producto_externo_id;
    public $pedido_id_externo;
    public $estado_externo;
    public $tracking_number;
    public $carrier;
    public $fecha_pedido_externo;
    public $fecha_envio;
    public $fecha_entrega_estimada;
    public $fecha_entrega_real;
    public $costo_producto;
    public $costo_envio;
    public $costo_total;
    public $datos_seguimiento;
    public $notas;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('pedidos_dropshipping');
        
        // Relaciones
        $this->belongsTo('pedido_id', Pedido::class, 'id', [
            'alias' => 'Pedido'
        ]);
        
        $this->belongsTo('proveedor_id', ProveedorDropshipping::class, 'id', [
            'alias' => 'Proveedor'
        ]);
        
        $this->belongsTo('producto_externo_id', ProductoExterno::class, 'id', [
            'alias' => 'ProductoExterno'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'pedido_id',
            new PresenceOf([
                'message' => 'El pedido es requerido'
            ])
        );

        $validator->add(
            'proveedor_id',
            new PresenceOf([
                'message' => 'El proveedor es requerido'
            ])
        );

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        if (is_array($this->datos_seguimiento)) {
            $this->datos_seguimiento = json_encode($this->datos_seguimiento);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->datos_seguimiento)) {
            $this->datos_seguimiento = json_decode($this->datos_seguimiento, true);
        }
    }

    /**
     * Obtiene los datos de seguimiento como array
     */
    public function getDatosSeguimiento()
    {
        if (is_array($this->datos_seguimiento)) {
            return $this->datos_seguimiento;
        }
        return [];
    }

    /**
     * Actualiza el estado del pedido
     */
    public function actualizarEstado($nuevoEstado, $datosAdicionales = [])
    {
        $estadoAnterior = $this->estado_externo;
        $this->estado_externo = $nuevoEstado;
        
        // Actualizar fechas según el estado
        switch ($nuevoEstado) {
            case 'shipped':
                if (!$this->fecha_envio) {
                    $this->fecha_envio = date('Y-m-d H:i:s');
                }
                break;
            case 'delivered':
                if (!$this->fecha_entrega_real) {
                    $this->fecha_entrega_real = date('Y-m-d H:i:s');
                }
                break;
        }
        
        // Actualizar datos de seguimiento
        if (!empty($datosAdicionales)) {
            $seguimiento = $this->getDatosSeguimiento();
            $seguimiento[] = [
                'fecha' => date('Y-m-d H:i:s'),
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'datos' => $datosAdicionales
            ];
            $this->datos_seguimiento = $seguimiento;
        }
        
        $resultado = $this->save();
        
        if ($resultado) {
            // Actualizar estado del pedido principal si es necesario
            $this->actualizarEstadoPedidoPrincipal();
            
            // Enviar notificación al cliente
            $this->enviarNotificacionCliente($estadoAnterior, $nuevoEstado);
        }
        
        return $resultado;
    }

    /**
     * Actualiza el estado del pedido principal basado en los pedidos de dropshipping
     */
    private function actualizarEstadoPedidoPrincipal()
    {
        $pedido = $this->Pedido;
        if (!$pedido) return;
        
        // Obtener todos los pedidos de dropshipping para este pedido
        $pedidosDropshipping = PedidoDropshipping::find([
            'conditions' => 'pedido_id = ?',
            'bind' => [$this->pedido_id]
        ]);
        
        $estados = [];
        foreach ($pedidosDropshipping as $pd) {
            $estados[] = $pd->estado_externo;
        }
        
        // Determinar el estado general
        if (in_array('pending', $estados) || in_array('processing', $estados)) {
            $estadoGeneral = 'procesando';
        } elseif (all_equal($estados, 'shipped')) {
            $estadoGeneral = 'enviado';
        } elseif (all_equal($estados, 'delivered')) {
            $estadoGeneral = 'entregado';
        } else {
            $estadoGeneral = 'procesando'; // Estado mixto
        }
        
        // Actualizar el pedido principal si es necesario
        if ($pedido->estado !== $estadoGeneral) {
            $pedido->estado = $estadoGeneral;
            $pedido->save();
        }
    }

    /**
     * Envía notificación al cliente sobre el cambio de estado
     */
    private function enviarNotificacionCliente($estadoAnterior, $estadoNuevo)
    {
        try {
            $pedido = $this->Pedido;
            $cliente = $pedido->Usuario;
            
            $mensajes = [
                'processing' => 'Tu pedido está siendo procesado',
                'shipped' => 'Tu pedido ha sido enviado',
                'delivered' => 'Tu pedido ha sido entregado',
                'cancelled' => 'Tu pedido ha sido cancelado'
            ];
            
            $mensaje = $mensajes[$estadoNuevo] ?? 'Estado de tu pedido actualizado';
            
            // Aquí se enviaría el email/SMS/notificación push
            // Por ahora solo registramos en logs
            error_log("Notificación para cliente {$cliente->email}: {$mensaje}");
            
        } catch (\Exception $e) {
            error_log("Error enviando notificación: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el estado en español
     */
    public function getEstadoEspanol()
    {
        $estados = [
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
            'returned' => 'Devuelto'
        ];
        
        return $estados[$this->estado_externo] ?? $this->estado_externo;
    }

    /**
     * Verifica si el pedido está en tránsito
     */
    public function estaEnTransito()
    {
        return in_array($this->estado_externo, ['shipped', 'in_transit']);
    }

    /**
     * Verifica si el pedido está completado
     */
    public function estaCompletado()
    {
        return $this->estado_externo === 'delivered';
    }

    /**
     * Obtiene la URL de seguimiento si está disponible
     */
    public function getUrlSeguimiento()
    {
        if (!$this->tracking_number || !$this->carrier) {
            return null;
        }
        
        $urls = [
            'DHL' => "https://www.dhl.com/track?tracking-id={$this->tracking_number}",
            'FedEx' => "https://www.fedex.com/track?tracknumber={$this->tracking_number}",
            'UPS' => "https://www.ups.com/track?tracknum={$this->tracking_number}",
            'USPS' => "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={$this->tracking_number}",
            'China Post' => "http://www.chinapost.com.cn/n/service/zhuisu/index.html?number={$this->tracking_number}"
        ];
        
        return $urls[$this->carrier] ?? null;
    }

    /**
     * Calcula los días de envío transcurridos
     */
    public function getDiasEnvio()
    {
        if (!$this->fecha_envio) {
            return 0;
        }
        
        $fechaEnvio = new \DateTime($this->fecha_envio);
        $fechaActual = new \DateTime();
        
        return $fechaActual->diff($fechaEnvio)->days;
    }

    /**
     * Verifica si el envío está retrasado
     */
    public function estaRetrasado()
    {
        if (!$this->fecha_entrega_estimada || $this->estaCompletado()) {
            return false;
        }
        
        $fechaEstimada = new \DateTime($this->fecha_entrega_estimada);
        $fechaActual = new \DateTime();
        
        return $fechaActual > $fechaEstimada;
    }

    /**
     * Obtiene pedidos por estado
     */
    public static function getByEstado($estado, $limite = null)
    {
        $conditions = [
            'conditions' => 'estado_externo = ?',
            'bind' => [$estado],
            'order' => 'created_at DESC'
        ];
        
        if ($limite) {
            $conditions['limit'] = $limite;
        }
        
        return self::find($conditions);
    }

    /**
     * Obtiene pedidos retrasados
     */
    public static function getRetrasados()
    {
        return self::find([
            'conditions' => 'fecha_entrega_estimada < CURRENT_DATE AND estado_externo NOT IN (?, ?)',
            'bind' => ['delivered', 'cancelled'],
            'order' => 'fecha_entrega_estimada ASC'
        ]);
    }

    /**
     * Obtiene estadísticas de pedidos de dropshipping
     */
    public static function getEstadisticas($dias = 30)
    {
        $db = \Phalcon\Di::getDefault()->getDb();
        
        return $db->fetchOne("
            SELECT 
                COUNT(*) as total_pedidos,
                COUNT(CASE WHEN estado_externo = 'pending' THEN 1 END) as pendientes,
                COUNT(CASE WHEN estado_externo = 'processing' THEN 1 END) as procesando,
                COUNT(CASE WHEN estado_externo = 'shipped' THEN 1 END) as enviados,
                COUNT(CASE WHEN estado_externo = 'delivered' THEN 1 END) as entregados,
                COUNT(CASE WHEN fecha_entrega_estimada < CURRENT_DATE AND estado_externo NOT IN ('delivered', 'cancelled') THEN 1 END) as retrasados,
                AVG(CASE WHEN fecha_entrega_real IS NOT NULL AND fecha_envio IS NOT NULL 
                    THEN EXTRACT(EPOCH FROM (fecha_entrega_real - fecha_envio))/86400 END) as dias_entrega_promedio
            FROM pedidos_dropshipping 
            WHERE created_at >= CURRENT_DATE - INTERVAL '{$dias} days'
        ");
    }
}

// Función auxiliar para verificar si todos los elementos de un array son iguales
if (!function_exists('all_equal')) {
    function all_equal($array, $value) {
        return count(array_unique($array)) === 1 && $array[0] === $value;
    }
}

