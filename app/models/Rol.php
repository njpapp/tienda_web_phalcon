<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;

class Rol extends Model
{
    public $id;
    public $nombre;
    public $descripcion;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('roles');
        
        // Relaciones
        $this->hasMany('id', Usuario::class, 'rol_id', [
            'alias' => 'usuarios'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('nombre', new PresenceOf([
            'message' => 'El nombre del rol es requerido'
        ]));

        $validator->add('nombre', new Uniqueness([
            'message' => 'Ya existe un rol con este nombre'
        ]));

        return $this->validate($validator);
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
     * Obtiene el rol por nombre
     */
    public static function obtenerPorNombre($nombre)
    {
        return self::findFirst([
            'conditions' => 'nombre = ?',
            'bind' => [$nombre]
        ]);
    }

    /**
     * Obtiene todos los roles activos
     */
    public static function obtenerActivos()
    {
        return self::find([
            'order' => 'nombre ASC'
        ]);
    }
}

