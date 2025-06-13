<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;
use Phalcon\Validation\Validator\Numericality;

class Producto extends Model
{
    public $id;
    public $sku;
    public $nombre;
    public $descripcion;
    public $categoria_id;
    public $proveedor_id;
    public $precio_compra;
    public $precio_venta;
    public $stock_actual;
    public $stock_minimo;
    public $stock_maximo;
    public $unidad_medida;
    public $peso;
    public $dimensiones;
    public $imagen_principal;
    public $activo;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('productos');
        
        // Relaciones
        $this->belongsTo('categoria_id', Categoria::class, 'id', [
            'alias' => 'categoria',
            'reusable' => true
        ]);
        
        $this->belongsTo('proveedor_id', Proveedor::class, 'id', [
            'alias' => 'proveedor',
            'reusable' => true
        ]);
        
        $this->hasMany('id', InventarioMovimiento::class, 'producto_id', [
            'alias' => 'movimientos'
        ]);
        
        $this->hasMany('id', ProductoImagen::class, 'producto_id', [
            'alias' => 'imagenes'
        ]);
        
        $this->hasMany('id', PedidoDetalle::class, 'producto_id', [
            'alias' => 'detallesPedido'
        ]);
        
        $this->hasMany('id', Carrito::class, 'producto_id', [
            'alias' => 'carritos'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('sku', new PresenceOf([
            'message' => 'El SKU es requerido'
        ]));

        $validator->add('sku', new Uniqueness([
            'message' => 'Ya existe un producto con este SKU'
        ]));

        $validator->add('nombre', new PresenceOf([
            'message' => 'El nombre del producto es requerido'
        ]));

        $validator->add('precio_compra', new PresenceOf([
            'message' => 'El precio de compra es requerido'
        ]));

        $validator->add('precio_compra', new Numericality([
            'message' => 'El precio de compra debe ser un número válido'
        ]));

        $validator->add('precio_venta', new PresenceOf([
            'message' => 'El precio de venta es requerido'
        ]));

        $validator->add('precio_venta', new Numericality([
            'message' => 'El precio de venta debe ser un número válido'
        ]));

        return $this->validate($validator);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        $this->activo = true;
        
        if (!$this->stock_actual) {
            $this->stock_actual = 0;
        }
        
        if (!$this->stock_minimo) {
            $this->stock_minimo = 0;
        }
        
        if (!$this->stock_maximo) {
            $this->stock_maximo = 1000;
        }
        
        if (!$this->unidad_medida) {
            $this->unidad_medida = 'unidad';
        }
    }

    public function beforeSave()
    {
        if ($this->getDirtyState() == Model::DIRTY_STATE_PERSISTENT) {
            $this->updated_at = date('Y-m-d H:i:s');
        }
    }

    /**
     * Obtiene todos los productos activos
     */
    public static function obtenerActivos()
    {
        return self::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC'
        ]);
    }

    /**
     * Busca productos por término
     */
    public static function buscar($termino, $limite = 20)
    {
        return self::find([
            'conditions' => 'activo = true AND (nombre ILIKE ?1 OR sku ILIKE ?1 OR descripcion ILIKE ?1)',
            'bind' => [1 => "%{$termino}%"],
            'order' => 'nombre ASC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene productos por categoría
     */
    public static function obtenerPorCategoria($categoriaId, $limite = null)
    {
        $params = [
            'conditions' => 'categoria_id = ? AND activo = true',
            'bind' => [$categoriaId],
            'order' => 'nombre ASC'
        ];
        
        if ($limite) {
            $params['limit'] = $limite;
        }
        
        return self::find($params);
    }

    /**
     * Obtiene productos con stock bajo
     */
    public static function obtenerStockBajo()
    {
        return self::find([
            'conditions' => 'stock_actual <= stock_minimo AND activo = true',
            'order' => 'stock_actual ASC'
        ]);
    }

    /**
     * Verifica si el producto tiene stock suficiente
     */
    public function tieneStock($cantidad = 1)
    {
        return $this->stock_actual >= $cantidad;
    }

    /**
     * Verifica si el stock está bajo
     */
    public function stockBajo()
    {
        return $this->stock_actual <= $this->stock_minimo;
    }

    /**
     * Calcula el margen de ganancia
     */
    public function getMargenGanancia()
    {
        if ($this->precio_compra <= 0) {
            return 0;
        }
        
        return (($this->precio_venta - $this->precio_compra) / $this->precio_compra) * 100;
    }

    /**
     * Obtiene el precio formateado
     */
    public function getPrecioFormateado()
    {
        return '$' . number_format($this->precio_venta, 2);
    }

    /**
     * Obtiene la URL de la imagen principal
     */
    public function getImagenUrl()
    {
        if ($this->imagen_principal) {
            return '/uploads/productos/' . $this->imagen_principal;
        }
        
        return '/img/producto-sin-imagen.png';
    }

    /**
     * Actualiza el stock del producto
     */
    public function actualizarStock($nuevoStock, $motivo = 'Ajuste manual', $usuarioId = null)
    {
        $stockAnterior = $this->stock_actual;
        $this->stock_actual = $nuevoStock;
        
        if ($this->save()) {
            // Registrar movimiento de inventario
            $movimiento = new InventarioMovimiento();
            $movimiento->producto_id = $this->id;
            $movimiento->tipo_movimiento = 'ajuste';
            $movimiento->cantidad = $nuevoStock - $stockAnterior;
            $movimiento->stock_anterior = $stockAnterior;
            $movimiento->stock_nuevo = $nuevoStock;
            $movimiento->motivo = $motivo;
            $movimiento->usuario_id = $usuarioId;
            
            return $movimiento->save();
        }
        
        return false;
    }

    /**
     * Reduce el stock del producto
     */
    public function reducirStock($cantidad, $motivo = 'Venta', $referencia = null, $usuarioId = null)
    {
        if (!$this->tieneStock($cantidad)) {
            return false;
        }
        
        $stockAnterior = $this->stock_actual;
        $this->stock_actual -= $cantidad;
        
        if ($this->save()) {
            // Registrar movimiento de inventario
            $movimiento = new InventarioMovimiento();
            $movimiento->producto_id = $this->id;
            $movimiento->tipo_movimiento = 'salida';
            $movimiento->cantidad = $cantidad;
            $movimiento->stock_anterior = $stockAnterior;
            $movimiento->stock_nuevo = $this->stock_actual;
            $movimiento->motivo = $motivo;
            $movimiento->referencia = $referencia;
            $movimiento->usuario_id = $usuarioId;
            
            return $movimiento->save();
        }
        
        return false;
    }

    /**
     * Aumenta el stock del producto
     */
    public function aumentarStock($cantidad, $motivo = 'Compra', $referencia = null, $usuarioId = null)
    {
        $stockAnterior = $this->stock_actual;
        $this->stock_actual += $cantidad;
        
        if ($this->save()) {
            // Registrar movimiento de inventario
            $movimiento = new InventarioMovimiento();
            $movimiento->producto_id = $this->id;
            $movimiento->tipo_movimiento = 'entrada';
            $movimiento->cantidad = $cantidad;
            $movimiento->stock_anterior = $stockAnterior;
            $movimiento->stock_nuevo = $this->stock_actual;
            $movimiento->motivo = $motivo;
            $movimiento->referencia = $referencia;
            $movimiento->usuario_id = $usuarioId;
            
            return $movimiento->save();
        }
        
        return false;
    }
}

