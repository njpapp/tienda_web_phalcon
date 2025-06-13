<?php

namespace App\Models;

use Phalcon\Mvc\Model;

class Sesion extends Model
{
    public $id;
    public $usuario_id;
    public $token;
    public $ip_address;
    public $user_agent;
    public $expires_at;
    public $created_at;

    public function initialize()
    {
        $this->setSource('sesiones');
        
        // Relaciones
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
     * Genera un nuevo token de sesión
     */
    public static function generarToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Crea una nueva sesión para un usuario
     */
    public static function crearSesion($usuarioId, $ipAddress = null, $userAgent = null, $duracionHoras = 2)
    {
        $sesion = new self();
        $sesion->usuario_id = $usuarioId;
        $sesion->token = self::generarToken();
        $sesion->ip_address = $ipAddress;
        $sesion->user_agent = $userAgent;
        $sesion->expires_at = date('Y-m-d H:i:s', strtotime("+{$duracionHoras} hours"));
        
        if ($sesion->save()) {
            return $sesion;
        }
        
        return false;
    }

    /**
     * Busca una sesión válida por token
     */
    public static function buscarPorToken($token)
    {
        return self::findFirst([
            'conditions' => 'token = ? AND expires_at > NOW()',
            'bind' => [$token]
        ]);
    }

    /**
     * Verifica si la sesión ha expirado
     */
    public function haExpirado()
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * Extiende la duración de la sesión
     */
    public function extender($horas = 2)
    {
        $this->expires_at = date('Y-m-d H:i:s', strtotime("+{$horas} hours"));
        return $this->save();
    }

    /**
     * Elimina sesiones expiradas
     */
    public static function limpiarExpiradas()
    {
        return self::find([
            'conditions' => 'expires_at < NOW()'
        ])->delete();
    }

    /**
     * Elimina todas las sesiones de un usuario
     */
    public static function eliminarPorUsuario($usuarioId)
    {
        return self::find([
            'conditions' => 'usuario_id = ?',
            'bind' => [$usuarioId]
        ])->delete();
    }
}

