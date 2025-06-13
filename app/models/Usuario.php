<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;

class Usuario extends Model
{
    public $id;
    public $uuid;
    public $email;
    public $password_hash;
    public $nombre;
    public $apellido;
    public $telefono;
    public $direccion;
    public $ciudad;
    public $codigo_postal;
    public $pais;
    public $rol_id;
    public $activo;
    public $email_verificado;
    public $ultimo_login;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('usuarios');
        
        // Relaciones
        $this->belongsTo('rol_id', Rol::class, 'id', [
            'alias' => 'rol',
            'reusable' => true
        ]);
        
        $this->hasMany('id', Pedido::class, 'cliente_id', [
            'alias' => 'pedidos'
        ]);
        
        $this->hasMany('id', TarjetaCliente::class, 'cliente_id', [
            'alias' => 'tarjetas'
        ]);
        
        $this->hasMany('id', Carrito::class, 'cliente_id', [
            'alias' => 'carrito'
        ]);
        
        $this->hasMany('id', Sesion::class, 'usuario_id', [
            'alias' => 'sesiones'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('email', new PresenceOf([
            'message' => 'El email es requerido'
        ]));

        $validator->add('email', new Email([
            'message' => 'El email debe tener un formato válido'
        ]));

        $validator->add('email', new Uniqueness([
            'message' => 'Este email ya está registrado'
        ]));

        $validator->add('nombre', new PresenceOf([
            'message' => 'El nombre es requerido'
        ]));

        $validator->add('apellido', new PresenceOf([
            'message' => 'El apellido es requerido'
        ]));

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        if ($this->getDirtyState() == Model::DIRTY_STATE_PERSISTENT) {
            $this->updated_at = date('Y-m-d H:i:s');
        }
    }

    public function beforeCreate()
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        $this->activo = true;
        $this->email_verificado = false;
        
        if (!$this->pais) {
            $this->pais = 'Colombia';
        }
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function tieneRol($nombreRol)
    {
        return $this->rol && $this->rol->nombre === $nombreRol;
    }

    /**
     * Verifica si el usuario es administrador
     */
    public function esAdmin()
    {
        return $this->tieneRol('admin');
    }

    /**
     * Verifica si el usuario es empleado
     */
    public function esEmpleado()
    {
        return $this->tieneRol('empleado');
    }

    /**
     * Verifica si el usuario es cliente
     */
    public function esCliente()
    {
        return $this->tieneRol('cliente');
    }

    /**
     * Obtiene el nombre completo del usuario
     */
    public function getNombreCompleto()
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    /**
     * Actualiza el último login
     */
    public function actualizarUltimoLogin()
    {
        $this->ultimo_login = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Verifica si la contraseña es correcta
     */
    public function verificarPassword($password)
    {
        return $this->getDI()->getSecurity()->checkHash($password, $this->password_hash);
    }

    /**
     * Establece una nueva contraseña
     */
    public function setPassword($password)
    {
        $this->password_hash = $this->getDI()->getSecurity()->hash($password);
    }

    /**
     * Obtiene estadísticas del cliente
     */
    public function getEstadisticas()
    {
        if (!$this->esCliente()) {
            return null;
        }

        $db = $this->getDI()->getDb();
        
        // Total de pedidos
        $totalPedidos = $this->countPedidos();
        
        // Total gastado
        $result = $db->fetchOne(
            "SELECT COALESCE(SUM(total), 0) as total_gastado 
             FROM pedidos 
             WHERE cliente_id = ? AND estado_id != (SELECT id FROM estados_pedido WHERE nombre = 'cancelado')",
            [$this->id]
        );
        $totalGastado = $result['total_gastado'];
        
        // Pedido promedio
        $promedioGasto = $totalPedidos > 0 ? $totalGastado / $totalPedidos : 0;
        
        // Último pedido
        $ultimoPedido = Pedido::findFirst([
            'conditions' => 'cliente_id = ?',
            'bind' => [$this->id],
            'order' => 'created_at DESC'
        ]);

        return [
            'total_pedidos' => $totalPedidos,
            'total_gastado' => $totalGastado,
            'promedio_gasto' => $promedioGasto,
            'ultimo_pedido' => $ultimoPedido ? $ultimoPedido->created_at : null
        ];
    }

    /**
     * Obtiene los pedidos recientes del cliente
     */
    public function getPedidosRecientes($limite = 5)
    {
        return $this->getPedidos([
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }
}

