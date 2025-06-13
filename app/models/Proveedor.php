<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;

class Proveedor extends Model
{
    public $id;
    public $nombre;
    public $contacto;
    public $email;
    public $telefono;
    public $direccion;
    public $ciudad;
    public $codigo_postal;
    public $pais;
    public $activo;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('proveedores');
        
        // Relaciones
        $this->hasMany('id', Producto::class, 'proveedor_id', [
            'alias' => 'productos'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('nombre', new PresenceOf([
            'message' => 'El nombre del proveedor es requerido'
        ]));

        return $this->validate($validator);
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        $this->activo = true;
    }

    public function beforeSave()
    {
        if ($this->getDirtyState() == Model::DIRTY_STATE_PERSISTENT) {
            $this->updated_at = date('Y-m-d H:i:s');
        }
    }

    /**
     * Obtiene todos los proveedores activos
     */
    public static function obtenerActivos()
    {
        return self::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC'
        ]);
    }

    /**
     * Cuenta los productos del proveedor
     */
    public function contarProductos()
    {
        return $this->countProductos(['conditions' => 'activo = true']);
    }
}

