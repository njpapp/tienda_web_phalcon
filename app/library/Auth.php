<?php

namespace App\Library;

use App\Models\Usuario;
use App\Models\Sesion;
use Phalcon\Di\Injectable;

class Auth extends Injectable
{
    const SESSION_KEY = 'auth_user_id';
    const TOKEN_KEY = 'auth_token';

    /**
     * Intenta autenticar un usuario
     */
    public function login($email, $password, $recordar = false)
    {
        // Buscar usuario por email
        $usuario = Usuario::findFirst([
            'conditions' => 'email = ? AND activo = true',
            'bind' => [$email]
        ]);

        if (!$usuario) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ];
        }

        // Verificar contraseña
        if (!$usuario->verificarPassword($password)) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ];
        }

        // Crear sesión
        $this->crearSesion($usuario, $recordar);

        // Actualizar último login
        $usuario->actualizarUltimoLogin();

        return [
            'success' => true,
            'message' => 'Login exitoso',
            'usuario' => $usuario
        ];
    }

    /**
     * Crea una sesión para el usuario
     */
    private function crearSesion($usuario, $recordar = false)
    {
        // Limpiar sesiones anteriores del usuario
        Sesion::eliminarPorUsuario($usuario->id);

        // Crear nueva sesión en base de datos
        $duracion = $recordar ? 24 * 7 : 2; // 7 días si recordar, 2 horas si no
        $sesion = Sesion::crearSesion(
            $usuario->id,
            $this->request->getClientAddress(),
            $this->request->getUserAgent(),
            $duracion
        );

        if ($sesion) {
            // Guardar en sesión PHP
            $this->session->set(self::SESSION_KEY, $usuario->id);
            $this->session->set(self::TOKEN_KEY, $sesion->token);

            // Si recordar, guardar en cookie
            if ($recordar) {
                $this->cookies->set(self::TOKEN_KEY, $sesion->token, time() + (86400 * 7)); // 7 días
            }
        }
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout()
    {
        $token = $this->session->get(self::TOKEN_KEY);
        
        if ($token) {
            // Eliminar sesión de base de datos
            $sesion = Sesion::buscarPorToken($token);
            if ($sesion) {
                $sesion->delete();
            }
        }

        // Limpiar sesión PHP
        $this->session->remove(self::SESSION_KEY);
        $this->session->remove(self::TOKEN_KEY);

        // Limpiar cookie
        $this->cookies->get(self::TOKEN_KEY)->delete();

        return true;
    }

    /**
     * Obtiene el usuario autenticado actual
     */
    public function getUsuario()
    {
        $usuarioId = $this->session->get(self::SESSION_KEY);
        
        if (!$usuarioId) {
            // Intentar recuperar de cookie
            $token = $this->cookies->get(self::TOKEN_KEY);
            if ($token && $token->getValue()) {
                $sesion = Sesion::buscarPorToken($token->getValue());
                if ($sesion && !$sesion->haExpirado()) {
                    $this->session->set(self::SESSION_KEY, $sesion->usuario_id);
                    $this->session->set(self::TOKEN_KEY, $sesion->token);
                    $usuarioId = $sesion->usuario_id;
                    
                    // Extender sesión
                    $sesion->extender();
                }
            }
        }

        if ($usuarioId) {
            return Usuario::findFirst([
                'conditions' => 'id = ? AND activo = true',
                'bind' => [$usuarioId]
            ]);
        }

        return null;
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public function estaAutenticado()
    {
        return $this->getUsuario() !== null;
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function tieneRol($rol)
    {
        $usuario = $this->getUsuario();
        return $usuario && $usuario->tieneRol($rol);
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
     * Verifica si el usuario puede acceder al panel administrativo
     */
    public function puedeAccederAdmin()
    {
        return $this->esAdmin() || $this->esEmpleado();
    }

    /**
     * Registra un nuevo usuario
     */
    public function registrar($datos)
    {
        $usuario = new Usuario();
        
        // Asignar datos
        $usuario->email = $datos['email'];
        $usuario->nombre = $datos['nombre'];
        $usuario->apellido = $datos['apellido'];
        $usuario->telefono = $datos['telefono'] ?? null;
        $usuario->direccion = $datos['direccion'] ?? null;
        $usuario->ciudad = $datos['ciudad'] ?? null;
        $usuario->codigo_postal = $datos['codigo_postal'] ?? null;
        $usuario->pais = $datos['pais'] ?? 'México';
        
        // Asignar rol de cliente por defecto
        $rolCliente = \App\Models\Rol::obtenerPorNombre('cliente');
        $usuario->rol_id = $rolCliente ? $rolCliente->id : 3;
        
        // Hashear contraseña
        $usuario->setPassword($datos['password']);

        if ($usuario->save()) {
            return [
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'usuario' => $usuario
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al registrar usuario',
                'errors' => $usuario->getMessages()
            ];
        }
    }

    /**
     * Cambia la contraseña del usuario actual
     */
    public function cambiarPassword($passwordActual, $passwordNuevo)
    {
        $usuario = $this->getUsuario();
        
        if (!$usuario) {
            return [
                'success' => false,
                'message' => 'Usuario no autenticado'
            ];
        }

        if (!$usuario->verificarPassword($passwordActual)) {
            return [
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ];
        }

        $usuario->setPassword($passwordNuevo);
        
        if ($usuario->save()) {
            return [
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al actualizar la contraseña'
            ];
        }
    }

    /**
     * Limpia sesiones expiradas (para ejecutar periódicamente)
     */
    public function limpiarSesionesExpiradas()
    {
        return Sesion::limpiarExpiradas();
    }
}

