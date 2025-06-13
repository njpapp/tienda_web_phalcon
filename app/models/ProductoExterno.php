<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Numericality;

class ProductoExterno extends Model
{
    public $id;
    public $proveedor_id;
    public $producto_id_externo;
    public $producto_id_interno;
    public $titulo;
    public $descripcion;
    public $precio_proveedor;
    public $precio_venta;
    public $margen_ganancia;
    public $disponible;
    public $stock_externo;
    public $url_producto;
    public $imagen_principal;
    public $imagenes_adicionales;
    public $categoria_externa;
    public $peso;
    public $dimensiones;
    public $tiempo_envio_min;
    public $tiempo_envio_max;
    public $calificacion;
    public $numero_reviews;
    public $datos_adicionales;
    public $ultima_actualizacion;
    public $created_at;

    public function initialize()
    {
        $this->setSource('productos_externos');
        
        // Relaciones
        $this->belongsTo('proveedor_id', ProveedorDropshipping::class, 'id', [
            'alias' => 'Proveedor'
        ]);
        
        $this->belongsTo('producto_id_interno', Producto::class, 'id', [
            'alias' => 'ProductoInterno'
        ]);
        
        $this->hasMany('id', PedidoDropshipping::class, 'producto_externo_id', [
            'alias' => 'PedidosDropshipping'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'proveedor_id',
            new PresenceOf([
                'message' => 'El proveedor es requerido'
            ])
        );

        $validator->add(
            'producto_id_externo',
            new PresenceOf([
                'message' => 'El ID del producto externo es requerido'
            ])
        );

        $validator->add(
            'titulo',
            new PresenceOf([
                'message' => 'El título del producto es requerido'
            ])
        );

        $validator->add(
            'precio_proveedor',
            new Numericality([
                'message' => 'El precio del proveedor debe ser numérico'
            ])
        );

        $validator->add(
            'precio_venta',
            new Numericality([
                'message' => 'El precio de venta debe ser numérico'
            ])
        );

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        // Convertir arrays a JSON
        if (is_array($this->imagenes_adicionales)) {
            $this->imagenes_adicionales = json_encode($this->imagenes_adicionales);
        }
        
        if (is_array($this->dimensiones)) {
            $this->dimensiones = json_encode($this->dimensiones);
        }
        
        if (is_array($this->datos_adicionales)) {
            $this->datos_adicionales = json_encode($this->datos_adicionales);
        }

        // Actualizar timestamp
        $this->ultima_actualizacion = date('Y-m-d H:i:s');
    }

    public function afterFetch()
    {
        // Convertir JSON a arrays
        if (is_string($this->imagenes_adicionales)) {
            $this->imagenes_adicionales = json_decode($this->imagenes_adicionales, true);
        }
        
        if (is_string($this->dimensiones)) {
            $this->dimensiones = json_decode($this->dimensiones, true);
        }
        
        if (is_string($this->datos_adicionales)) {
            $this->datos_adicionales = json_decode($this->datos_adicionales, true);
        }
    }

    /**
     * Calcula el margen de ganancia
     */
    public function calcularMargen()
    {
        if ($this->precio_proveedor > 0) {
            return (($this->precio_venta - $this->precio_proveedor) / $this->precio_proveedor) * 100;
        }
        return 0;
    }

    /**
     * Establece el precio de venta basado en un margen
     */
    public function setPrecioConMargen($margen)
    {
        $this->precio_venta = $this->precio_proveedor * (1 + ($margen / 100));
        return $this;
    }

    /**
     * Verifica si el producto está disponible
     */
    public function estaDisponible()
    {
        return $this->disponible && $this->stock_externo > 0;
    }

    /**
     * Obtiene las imágenes como array
     */
    public function getImagenes()
    {
        $imagenes = [$this->imagen_principal];
        
        if (is_array($this->imagenes_adicionales)) {
            $imagenes = array_merge($imagenes, $this->imagenes_adicionales);
        }
        
        return array_filter($imagenes);
    }

    /**
     * Obtiene las dimensiones como array
     */
    public function getDimensiones()
    {
        if (is_array($this->dimensiones)) {
            return $this->dimensiones;
        }
        return [];
    }

    /**
     * Obtiene datos adicionales como array
     */
    public function getDatosAdicionales()
    {
        if (is_array($this->datos_adicionales)) {
            return $this->datos_adicionales;
        }
        return [];
    }

    /**
     * Sincroniza con el producto interno
     */
    public function sincronizarConProductoInterno()
    {
        if (!$this->producto_id_interno) {
            // Crear nuevo producto interno
            $producto = new Producto();
            $producto->nombre = $this->titulo;
            $producto->descripcion = $this->descripcion;
            $producto->precio_venta = $this->precio_venta;
            $producto->precio_compra = $this->precio_proveedor;
            $producto->stock_actual = 0; // Los productos dropshipping no tienen stock físico
            $producto->stock_minimo = 0;
            $producto->activo = $this->disponible;
            $producto->es_dropshipping = true;
            
            if ($producto->save()) {
                $this->producto_id_interno = $producto->id;
                $this->save();
            }
        } else {
            // Actualizar producto existente
            $producto = $this->ProductoInterno;
            if ($producto) {
                $producto->precio_venta = $this->precio_venta;
                $producto->precio_compra = $this->precio_proveedor;
                $producto->activo = $this->disponible;
                $producto->save();
            }
        }
    }

    /**
     * Actualiza la información del producto desde el proveedor
     */
    public function actualizarDesdeProveedor($datosProveedor)
    {
        $this->titulo = $datosProveedor['titulo'] ?? $this->titulo;
        $this->descripcion = $datosProveedor['descripcion'] ?? $this->descripcion;
        $this->precio_proveedor = $datosProveedor['precio'] ?? $this->precio_proveedor;
        $this->disponible = $datosProveedor['disponible'] ?? $this->disponible;
        $this->stock_externo = $datosProveedor['stock'] ?? $this->stock_externo;
        $this->imagen_principal = $datosProveedor['imagen'] ?? $this->imagen_principal;
        $this->imagenes_adicionales = $datosProveedor['imagenes_adicionales'] ?? $this->imagenes_adicionales;
        $this->calificacion = $datosProveedor['calificacion'] ?? $this->calificacion;
        $this->numero_reviews = $datosProveedor['numero_reviews'] ?? $this->numero_reviews;
        
        // Recalcular precio de venta manteniendo el margen
        if (isset($datosProveedor['precio'])) {
            $margenActual = $this->calcularMargen();
            if ($margenActual > 0) {
                $this->setPrecioConMargen($margenActual);
            }
        }
        
        return $this->save();
    }

    /**
     * Obtiene productos por proveedor
     */
    public static function getByProveedor($proveedorId, $limit = null)
    {
        $conditions = [
            'conditions' => 'proveedor_id = ?',
            'bind' => [$proveedorId],
            'order' => 'created_at DESC'
        ];
        
        if ($limit) {
            $conditions['limit'] = $limit;
        }
        
        return self::find($conditions);
    }

    /**
     * Obtiene productos disponibles
     */
    public static function getDisponibles($limit = null)
    {
        $conditions = [
            'conditions' => 'disponible = true AND stock_externo > 0',
            'order' => 'created_at DESC'
        ];
        
        if ($limit) {
            $conditions['limit'] = $limit;
        }
        
        return self::find($conditions);
    }

    /**
     * Busca productos por término
     */
    public static function buscar($termino, $proveedorId = null)
    {
        $conditions = ['titulo ILIKE ?'];
        $bind = ["%{$termino}%"];
        
        if ($proveedorId) {
            $conditions[] = 'proveedor_id = ?';
            $bind[] = $proveedorId;
        }
        
        return self::find([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'created_at DESC'
        ]);
    }

    /**
     * Obtiene productos que necesitan actualización
     */
    public static function getNecesitanActualizacion($horas = 24)
    {
        return self::find([
            'conditions' => 'ultima_actualizacion < ?',
            'bind' => [date('Y-m-d H:i:s', strtotime("-{$horas} hours"))],
            'order' => 'ultima_actualizacion ASC'
        ]);
    }
}

