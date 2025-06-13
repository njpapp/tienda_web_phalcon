<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Numericality;

class Pedido extends Model
{
    public $id;
    public $numero_pedido;
    public $cliente_id;
    public $estado_id;
    public $subtotal;
    public $impuestos;
    public $descuento;
    public $total;
    public $metodo_pago;
    public $direccion_envio;
    public $notas;
    public $fecha_pedido;
    public $fecha_envio;
    public $fecha_entrega;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('pedidos');
        
        // Relaciones
        $this->belongsTo('cliente_id', Usuario::class, 'id', [
            'alias' => 'cliente',
            'reusable' => true
        ]);
        
        $this->belongsTo('estado_id', EstadoPedido::class, 'id', [
            'alias' => 'estado',
            'reusable' => true
        ]);
        
        $this->hasMany('id', PedidoDetalle::class, 'pedido_id', [
            'alias' => 'detalles'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('numero_pedido', new PresenceOf([
            'message' => 'El número de pedido es requerido'
        ]));

        $validator->add('cliente_id', new PresenceOf([
            'message' => 'El cliente es requerido'
        ]));

        $validator->add('subtotal', new Numericality([
            'message' => 'El subtotal debe ser un número válido'
        ]));

        $validator->add('total', new Numericality([
            'message' => 'El total debe ser un número válido'
        ]));

        return $this->validate($validator);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        $this->fecha_pedido = date('Y-m-d H:i:s');
        
        if (!$this->numero_pedido) {
            $this->numero_pedido = $this->generarNumeroPedido();
        }
        
        if (!$this->estado_id) {
            $estadoPendiente = EstadoPedido::obtenerPorNombre('pendiente');
            $this->estado_id = $estadoPendiente ? $estadoPendiente->id : 1;
        }
        
        if (!$this->impuestos) {
            $this->impuestos = 0;
        }
        
        if (!$this->descuento) {
            $this->descuento = 0;
        }
    }

    public function beforeSave()
    {
        if ($this->getDirtyState() == Model::DIRTY_STATE_PERSISTENT) {
            $this->updated_at = date('Y-m-d H:i:s');
        }
    }

    /**
     * Genera un número de pedido único
     */
    private function generarNumeroPedido()
    {
        $año = date('Y');
        $mes = date('m');
        
        // Obtener el último número del mes
        $ultimoPedido = self::findFirst([
            'conditions' => "numero_pedido LIKE ?",
            'bind' => ["PED-{$año}{$mes}-%"],
            'order' => 'id DESC'
        ]);
        
        $siguiente = 1;
        if ($ultimoPedido) {
            $partes = explode('-', $ultimoPedido->numero_pedido);
            if (count($partes) >= 3) {
                $siguiente = intval($partes[2]) + 1;
            }
        }
        
        return sprintf("PED-%s%s-%03d", $año, $mes, $siguiente);
    }

    /**
     * Obtiene pedidos por cliente
     */
    public static function obtenerPorCliente($clienteId, $limite = null)
    {
        $params = [
            'conditions' => 'cliente_id = ?',
            'bind' => [$clienteId],
            'order' => 'created_at DESC'
        ];
        
        if ($limite) {
            $params['limit'] = $limite;
        }
        
        return self::find($params);
    }

    /**
     * Obtiene pedidos recientes
     */
    public static function obtenerRecientes($limite = 20)
    {
        return self::find([
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene el total formateado
     */
    public function getTotalFormateado()
    {
        return '$' . number_format($this->total, 2);
    }

    /**
     * Verifica si el pedido puede ser cancelado
     */
    public function puedeSerCancelado()
    {
        return in_array($this->estado->nombre, ['pendiente', 'procesando']);
    }

    /**
     * Verifica si el pedido puede ser editado
     */
    public function puedeSerEditado()
    {
        return $this->estado->nombre === 'pendiente';
    }

    /**
     * Cambia el estado del pedido
     */
    public function cambiarEstado($nuevoEstadoId, $usuarioId = null)
    {
        $estadoAnterior = $this->estado_id;
        $this->estado_id = $nuevoEstadoId;
        
        // Actualizar fechas según el estado
        $nuevoEstado = EstadoPedido::findFirst($nuevoEstadoId);
        if ($nuevoEstado) {
            switch ($nuevoEstado->nombre) {
                case 'enviado':
                    $this->fecha_envio = date('Y-m-d H:i:s');
                    break;
                case 'entregado':
                    if (!$this->fecha_envio) {
                        $this->fecha_envio = date('Y-m-d H:i:s');
                    }
                    $this->fecha_entrega = date('Y-m-d H:i:s');
                    break;
            }
        }
        
        if ($this->save()) {
            // Registrar el cambio de estado
            $this->getDI()->getLogger()->info(
                "Pedido {$this->numero_pedido} cambió de estado {$estadoAnterior} a {$nuevoEstadoId}",
                ['usuario_id' => $usuarioId, 'pedido_id' => $this->id]
            );
            return true;
        }
        
        return false;
    }

    /**
     * Calcula el total del pedido basado en los detalles
     */
    public function calcularTotal()
    {
        $subtotal = 0;
        
        foreach ($this->detalles as $detalle) {
            $subtotal += $detalle->subtotal;
        }
        
        $this->subtotal = $subtotal;
        $this->total = $subtotal + $this->impuestos - $this->descuento;
        
        return $this->save();
    }

    /**
     * Obtiene el resumen del pedido
     */
    public function getResumen()
    {
        return [
            'numero' => $this->numero_pedido,
            'fecha' => $this->fecha_pedido,
            'cliente' => $this->cliente ? $this->cliente->getNombreCompleto() : 'N/A',
            'estado' => $this->estado ? $this->estado->nombre : 'N/A',
            'total' => $this->total,
            'items' => $this->countDetalles()
        ];
    }
}

