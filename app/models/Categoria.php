<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;

class Categoria extends Model
{
    public $id;
    public $nombre;
    public $descripcion;
    public $imagen;
    public $activo;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('categorias');
        
        // Relaciones
        $this->hasMany('id', Producto::class, 'categoria_id', [
            'alias' => 'productos'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('nombre', new PresenceOf([
            'message' => 'El nombre de la categoría es requerido'
        ]));

        $validator->add('nombre', new Uniqueness([
            'message' => 'Ya existe una categoría con este nombre'
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
     * Obtiene todas las categorías activas
     */
    public static function obtenerActivas()
    {
        return self::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC'
        ]);
    }

    /**
     * Cuenta los productos de la categoría
     */
    public function contarProductos()
    {
        return $this->countProductos(['conditions' => 'activo = true']);
    }
}

