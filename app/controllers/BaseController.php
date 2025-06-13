<?php

namespace App\Controllers;

use Phalcon\Mvc\Controller;
use App\Library\AuthMiddleware;

class BaseController extends Controller
{
    /**
     * Usuario autenticado actual
     */
    protected $usuario;

    /**
     * Servicio de autenticación
     */
    protected $auth;

    /**
     * Middleware de autenticación
     */
    protected $authMiddleware;

    public function initialize()
    {
        // Inicializar servicios
        $this->auth = $this->getDI()->getAuth();
        $this->authMiddleware = new AuthMiddleware();
        $this->authMiddleware->setDI($this->getDI());

        // Obtener usuario autenticado
        $this->usuario = $this->auth->getUsuario();

        // Hacer disponible el usuario en las vistas
        $this->view->setVar('usuarioActual', $this->usuario);
        $this->view->setVar('auth', $this->auth);

        // Configurar zona horaria
        date_default_timezone_set($this->config->timezone);

        // Limpiar sesiones expiradas ocasionalmente (1% de probabilidad)
        if (rand(1, 100) === 1) {
            $this->auth->limpiarSesionesExpiradas();
        }
    }

    /**
     * Verifica si el usuario está autenticado
     */
    protected function requireAuth()
    {
        if (!$this->auth->estaAutenticado()) {
            $this->guardarUrlDestino();
            $this->flashSession->error('Debes iniciar sesión para acceder a esta página');
            return $this->response->redirect('/login');
        }
        return true;
    }

    /**
     * Verifica si el usuario es administrador
     */
    protected function requireAdmin()
    {
        if (!$this->requireAuth()) {
            return false;
        }

        if (!$this->auth->esAdmin()) {
            $this->flashSession->error('No tienes permisos de administrador');
            return $this->response->redirect('/');
        }
        return true;
    }

    /**
     * Verifica si el usuario puede acceder al panel administrativo
     */
    protected function requireAdminAccess()
    {
        if (!$this->requireAuth()) {
            return false;
        }

        if (!$this->auth->puedeAccederAdmin()) {
            $this->flashSession->error('No tienes permisos para acceder al panel administrativo');
            return $this->response->redirect('/');
        }
        return true;
    }

    /**
     * Verifica si el usuario es cliente
     */
    protected function requireCliente()
    {
        if (!$this->requireAuth()) {
            return false;
        }

        if (!$this->auth->esCliente()) {
            $this->flashSession->error('Esta sección es solo para clientes');
            return $this->response->redirect('/admin');
        }
        return true;
    }

    /**
     * Verifica un permiso específico
     */
    protected function requirePermiso($permiso)
    {
        if (!$this->requireAuth()) {
            return false;
        }

        return $this->authMiddleware->requierePermiso($permiso);
    }

    /**
     * Guarda la URL actual para redirigir después del login
     */
    private function guardarUrlDestino()
    {
        $uri = $this->request->getURI();
        if ($uri && $uri !== '/login' && $uri !== '/logout') {
            $this->session->set('redirect_url', $uri);
        }
    }

    /**
     * Redirige al usuario según su rol después del login
     */
    protected function redirigirSegunRol()
    {
        // Verificar si hay URL de destino guardada
        $urlDestino = $this->session->get('redirect_url');
        if ($urlDestino) {
            $this->session->remove('redirect_url');
            return $this->response->redirect($urlDestino);
        }

        // Redirigir según el rol del usuario
        if ($this->auth->esAdmin() || $this->auth->esEmpleado()) {
            return $this->response->redirect('/admin');
        } elseif ($this->auth->esCliente()) {
            return $this->response->redirect('/cliente');
        } else {
            return $this->response->redirect('/');
        }
    }

    /**
     * Respuesta JSON para APIs
     */
    protected function jsonResponse($data, $statusCode = 200)
    {
        $this->response->setStatusCode($statusCode);
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->response;
    }

    /**
     * Respuesta de error JSON
     */
    protected function jsonError($message, $statusCode = 400, $errors = null)
    {
        $data = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $data['errors'] = $errors;
        }

        return $this->jsonResponse($data, $statusCode);
    }

    /**
     * Respuesta de éxito JSON
     */
    protected function jsonSuccess($message, $data = null)
    {
        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data) {
            $response['data'] = $data;
        }

        return $this->jsonResponse($response);
    }

    /**
     * Valida datos de entrada
     */
    protected function validarDatos($datos, $reglas)
    {
        $errores = [];

        foreach ($reglas as $campo => $regla) {
            $valor = $datos[$campo] ?? null;

            // Verificar si es requerido
            if (isset($regla['required']) && $regla['required'] && empty($valor)) {
                $errores[$campo] = $regla['message'] ?? "El campo {$campo} es requerido";
                continue;
            }

            // Si el valor está vacío y no es requerido, continuar
            if (empty($valor)) {
                continue;
            }

            // Validar email
            if (isset($regla['email']) && $regla['email'] && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                $errores[$campo] = "El campo {$campo} debe ser un email válido";
            }

            // Validar longitud mínima
            if (isset($regla['min_length']) && strlen($valor) < $regla['min_length']) {
                $errores[$campo] = "El campo {$campo} debe tener al menos {$regla['min_length']} caracteres";
            }

            // Validar longitud máxima
            if (isset($regla['max_length']) && strlen($valor) > $regla['max_length']) {
                $errores[$campo] = "El campo {$campo} no puede tener más de {$regla['max_length']} caracteres";
            }

            // Validar número
            if (isset($regla['numeric']) && $regla['numeric'] && !is_numeric($valor)) {
                $errores[$campo] = "El campo {$campo} debe ser un número";
            }
        }

        return $errores;
    }

    /**
     * Obtiene parámetros de paginación
     */
    protected function obtenerPaginacion()
    {
        $page = (int) $this->request->getQuery('page', 'int', 1);
        $limit = (int) $this->request->getQuery('limit', 'int', $this->config->pagination->limit);
        
        // Limitar el máximo de elementos por página
        if ($limit > $this->config->pagination->maxLimit) {
            $limit = $this->config->pagination->maxLimit;
        }

        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Registra actividad del usuario
     */
    protected function registrarActividad($accion, $descripcion = null, $datos = null)
    {
        if ($this->usuario) {
            $this->logger->info("Usuario {$this->usuario->id} - {$accion}: " . ($descripcion ?: ''), $datos ?: []);
        }
    }
}

