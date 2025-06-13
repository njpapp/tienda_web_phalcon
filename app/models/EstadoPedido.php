<?php

namespace App\Models;

use Phalcon\Mvc\Model;

class EstadoPedido extends Model
{
    public $id;
    public $nombre;
    public $descripcion;
    public $color;
    public $created_at;

    public function initialize()
    {
        $this->setSource('estados_pedido');
        
        // Relaciones
        $this->hasMany('id', Pedido::class, 'estado_id', [
            'alias' => 'pedidos'
        ]);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
    }

    /**
     * Obtiene todos los estados
     */
    public static function obtenerTodos()
    {
        return self::find([
            'order' => 'id ASC'
        ]);
    }

    /**
     * Obtiene estado por nombre
     */
    public static function obtenerPorNombre($nombre)
    {
        return self::findFirst([
            'conditions' => 'nombre = ?',
            'bind' => [$nombre]
        ]);
    }
}

