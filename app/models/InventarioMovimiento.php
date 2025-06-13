<?php

namespace App\Models;

use Phalcon\Mvc\Model;

class InventarioMovimiento extends Model
{
    public $id;
    public $producto_id;
    public $tipo_movimiento;
    public $cantidad;
    public $stock_anterior;
    public $stock_nuevo;
    public $motivo;
    public $referencia;
    public $usuario_id;
    public $created_at;

    public function initialize()
    {
        $this->setSource('inventario_movimientos');
        
        // Relaciones
        $this->belongsTo('producto_id', Producto::class, 'id', [
            'alias' => 'producto',
            'reusable' => true
        ]);
        
        $this->belongsTo('usuario_id', Usuario::class, 'id', [
            'alias' => 'usuario',
            'reusable' => true
        ]);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
    }

    /**
     * Obtiene movimientos por producto
     */
    public static function obtenerPorProducto($productoId, $limite = 50)
    {
        return self::find([
            'conditions' => 'producto_id = ?',
            'bind' => [$productoId],
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene movimientos recientes
     */
    public static function obtenerRecientes($limite = 20)
    {
        return self::find([
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene el tipo de movimiento formateado
     */
    public function getTipoFormateado()
    {
        $tipos = [
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'ajuste' => 'Ajuste'
        ];
        
        return $tipos[$this->tipo_movimiento] ?? $this->tipo_movimiento;
    }

    /**
     * Obtiene la clase CSS para el tipo de movimiento
     */
    public function getTipoClase()
    {
        $clases = [
            'entrada' => 'success',
            'salida' => 'danger',
            'ajuste' => 'warning'
        ];
        
        return $clases[$this->tipo_movimiento] ?? 'secondary';
    }
}

