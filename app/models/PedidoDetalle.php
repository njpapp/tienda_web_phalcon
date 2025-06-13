<?php

namespace App\Models;

use Phalcon\Mvc\Model;

class PedidoDetalle extends Model
{
    public $id;
    public $pedido_id;
    public $producto_id;
    public $cantidad;
    public $precio_unitario;
    public $subtotal;
    public $created_at;

    public function initialize()
    {
        $this->setSource('pedido_detalles');
        
        // Relaciones
        $this->belongsTo('pedido_id', Pedido::class, 'id', [
            'alias' => 'pedido',
            'reusable' => true
        ]);
        
        $this->belongsTo('producto_id', Producto::class, 'id', [
            'alias' => 'producto',
            'reusable' => true
        ]);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        
        // Calcular subtotal si no está establecido
        if (!$this->subtotal) {
            $this->subtotal = $this->cantidad * $this->precio_unitario;
        }
    }

    public function beforeSave()
    {
        // Recalcular subtotal
        $this->subtotal = $this->cantidad * $this->precio_unitario;
    }

    /**
     * Obtiene el subtotal formateado
     */
    public function getSubtotalFormateado()
    {
        return '$' . number_format($this->subtotal, 2);
    }

    /**
     * Obtiene el precio unitario formateado
     */
    public function getPrecioFormateado()
    {
        return '$' . number_format($this->precio_unitario, 2);
    }
}

// Modelo ProductoImagen
class ProductoImagen extends Model
{
    public $id;
    public $producto_id;
    public $url;
    public $alt_text;
    public $orden;
    public $created_at;

    public function initialize()
    {
        $this->setSource('producto_imagenes');
        
        // Relaciones
        $this->belongsTo('producto_id', Producto::class, 'id', [
            'alias' => 'producto',
            'reusable' => true
        ]);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        
        if (!$this->orden) {
            $this->orden = 0;
        }
    }

    /**
     * Obtiene imágenes por producto
     */
    public static function obtenerPorProducto($productoId)
    {
        return self::find([
            'conditions' => 'producto_id = ?',
            'bind' => [$productoId],
            'order' => 'orden ASC, id ASC'
        ]);
    }
}

// Modelo Carrito
class Carrito extends Model
{
    public $id;
    public $cliente_id;
    public $producto_id;
    public $cantidad;
    public $precio_unitario;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('carritos');
        
        // Relaciones
        $this->belongsTo('cliente_id', Usuario::class, 'id', [
            'alias' => 'cliente',
            'reusable' => true
        ]);
        
        $this->belongsTo('producto_id', Producto::class, 'id', [
            'alias' => 'producto',
            'reusable' => true
        ]);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
    }

    public function beforeSave()
    {
        if ($this->getDirtyState() == Model::DIRTY_STATE_PERSISTENT) {
            $this->updated_at = date('Y-m-d H:i:s');
        }
    }

    /**
     * Obtiene items del carrito por cliente
     */
    public static function obtenerPorCliente($clienteId)
    {
        return self::find([
            'conditions' => 'cliente_id = ?',
            'bind' => [$clienteId],
            'order' => 'created_at DESC'
        ]);
    }

    /**
     * Calcula el subtotal del item
     */
    public function getSubtotal()
    {
        return $this->cantidad * $this->precio_unitario;
    }

    /**
     * Obtiene el subtotal formateado
     */
    public function getSubtotalFormateado()
    {
        return '$' . number_format($this->getSubtotal(), 2);
    }

    /**
     * Agrega un producto al carrito
     */
    public static function agregar($clienteId, $productoId, $cantidad = 1)
    {
        // Verificar si ya existe el producto en el carrito
        $item = self::findFirst([
            'conditions' => 'cliente_id = ? AND producto_id = ?',
            'bind' => [$clienteId, $productoId]
        ]);

        $producto = Producto::findFirst($productoId);
        if (!$producto || !$producto->activo) {
            return false;
        }

        if ($item) {
            // Actualizar cantidad
            $item->cantidad += $cantidad;
            $item->precio_unitario = $producto->precio_venta;
            return $item->save();
        } else {
            // Crear nuevo item
            $item = new self();
            $item->cliente_id = $clienteId;
            $item->producto_id = $productoId;
            $item->cantidad = $cantidad;
            $item->precio_unitario = $producto->precio_venta;
            return $item->save();
        }
    }

    /**
     * Calcula el total del carrito
     */
    public static function calcularTotal($clienteId)
    {
        $items = self::obtenerPorCliente($clienteId);
        $total = 0;
        
        foreach ($items as $item) {
            $total += $item->getSubtotal();
        }
        
        return $total;
    }

    /**
     * Vacía el carrito del cliente
     */
    public static function vaciar($clienteId)
    {
        $items = self::obtenerPorCliente($clienteId);
        return $items->delete();
    }
}

// Modelo TarjetaCliente
class TarjetaCliente extends Model
{
    public $id;
    public $cliente_id;
    public $tipo;
    public $marca;
    public $ultimos_4_digitos;
    public $nombre_titular;
    public $mes_expiracion;
    public $año_expiracion;
    public $es_principal;
    public $activo;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('tarjetas_cliente');
        
        // Relaciones
        $this->belongsTo('cliente_id', Usuario::class, 'id', [
            'alias' => 'cliente',
            'reusable' => true
        ]);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        $this->activo = true;
        
        if (!$this->es_principal) {
            $this->es_principal = false;
        }
    }

    public function beforeSave()
    {
        if ($this->getDirtyState() == Model::DIRTY_STATE_PERSISTENT) {
            $this->updated_at = date('Y-m-d H:i:s');
        }
        
        // Si se marca como principal, desmarcar las demás
        if ($this->es_principal) {
            self::find([
                'conditions' => 'cliente_id = ? AND id != ? AND es_principal = true',
                'bind' => [$this->cliente_id, $this->id ?: 0]
            ])->update(['es_principal' => false]);
        }
    }

    /**
     * Obtiene tarjetas por cliente
     */
    public static function obtenerPorCliente($clienteId)
    {
        return self::find([
            'conditions' => 'cliente_id = ? AND activo = true',
            'bind' => [$clienteId],
            'order' => 'es_principal DESC, created_at DESC'
        ]);
    }

    /**
     * Obtiene la tarjeta principal del cliente
     */
    public static function obtenerPrincipal($clienteId)
    {
        return self::findFirst([
            'conditions' => 'cliente_id = ? AND es_principal = true AND activo = true',
            'bind' => [$clienteId]
        ]);
    }

    /**
     * Obtiene el número enmascarado
     */
    public function getNumeroEnmascarado()
    {
        return "**** **** **** {$this->ultimos_4_digitos}";
    }

    /**
     * Verifica si la tarjeta ha expirado
     */
    public function haExpirado()
    {
        $fechaExpiracion = mktime(0, 0, 0, $this->mes_expiracion + 1, 0, $this->año_expiracion);
        return time() > $fechaExpiracion;
    }

    /**
     * Obtiene la fecha de expiración formateada
     */
    public function getFechaExpiracion()
    {
        return sprintf("%02d/%d", $this->mes_expiracion, $this->año_expiracion);
    }
}

