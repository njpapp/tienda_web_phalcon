<?php

namespace App\Library;

use Phalcon\Di\Injectable;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;

class AuthMiddleware extends Injectable
{
    /**
     * Rutas públicas que no requieren autenticación
     */
    private $rutasPublicas = [
        'index' => ['index'],
        'auth' => ['login', 'authenticate', 'register', 'create', 'logout'],
        'tienda' => ['index', 'categoria', 'producto'],
        'api' => ['buscarProductos'],
        'errors' => ['show404', 'show500']
    ];

    /**
     * Rutas que requieren rol de administrador o empleado
     */
    private $rutasAdmin = [
        'admin' => '*',
        'productos' => '*',
        'inventario' => '*',
        'ventas' => '*',
        'reportes' => '*',
        'categorias' => '*',
        'proveedores' => '*'
    ];

    /**
     * Rutas que requieren rol de cliente
     */
    private $rutasCliente = [
        'cliente' => '*',
        'carrito' => '*',
        'checkout' => '*'
    ];

    /**
     * Ejecuta antes de cada acción del controlador
     */
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher)
    {
        $controllerName = $dispatcher->getControllerName();
        $actionName = $dispatcher->getActionName();

        // Verificar si la ruta es pública
        if ($this->esRutaPublica($controllerName, $actionName)) {
            return true;
        }

        // Obtener usuario autenticado
        $auth = $this->getDI()->getAuth();
        $usuario = $auth->getUsuario();

        // Si no hay usuario autenticado, redirigir a login
        if (!$usuario) {
            $this->guardarUrlDestino();
            $this->flashSession->error('Debes iniciar sesión para acceder a esta página');
            $this->response->redirect('/login');
            return false;
        }

        // Verificar permisos específicos por tipo de ruta
        if ($this->esRutaAdmin($controllerName)) {
            if (!$auth->puedeAccederAdmin()) {
                $this->flashSession->error('No tienes permisos para acceder a esta sección');
                $this->response->redirect('/');
                return false;
            }
        } elseif ($this->esRutaCliente($controllerName)) {
            if (!$auth->esCliente()) {
                $this->flashSession->error('Esta sección es solo para clientes');
                $this->response->redirect('/admin');
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si una ruta es pública
     */
    private function esRutaPublica($controller, $action)
    {
        if (!isset($this->rutasPublicas[$controller])) {
            return false;
        }

        $accionesPermitidas = $this->rutasPublicas[$controller];
        
        if ($accionesPermitidas === '*') {
            return true;
        }

        return in_array($action, $accionesPermitidas);
    }

    /**
     * Verifica si una ruta requiere permisos de administrador
     */
    private function esRutaAdmin($controller)
    {
        return isset($this->rutasAdmin[$controller]);
    }

    /**
     * Verifica si una ruta requiere permisos de cliente
     */
    private function esRutaCliente($controller)
    {
        return isset($this->rutasCliente[$controller]);
    }

    /**
     * Guarda la URL de destino para redirigir después del login
     */
    private function guardarUrlDestino()
    {
        $uri = $this->request->getURI();
        if ($uri && $uri !== '/login' && $uri !== '/logout') {
            $this->session->set('redirect_url', $uri);
        }
    }

    /**
     * Obtiene y limpia la URL de destino guardada
     */
    public function obtenerUrlDestino()
    {
        $url = $this->session->get('redirect_url', '/');
        $this->session->remove('redirect_url');
        return $url;
    }

    /**
     * Verifica permisos específicos para acciones
     */
    public function verificarPermiso($permiso)
    {
        $auth = $this->getDI()->getAuth();
        $usuario = $auth->getUsuario();

        if (!$usuario) {
            return false;
        }

        switch ($permiso) {
            case 'crear_usuario':
                return $auth->esAdmin();
            
            case 'editar_usuario':
                return $auth->esAdmin();
            
            case 'eliminar_usuario':
                return $auth->esAdmin();
            
            case 'gestionar_inventario':
                return $auth->puedeAccederAdmin();
            
            case 'ver_reportes':
                return $auth->puedeAccederAdmin();
            
            case 'procesar_ventas':
                return $auth->puedeAccederAdmin();
            
            case 'gestionar_productos':
                return $auth->puedeAccederAdmin();
            
            case 'ver_todas_las_ventas':
                return $auth->puedeAccederAdmin();
            
            case 'cambiar_estado_pedido':
                return $auth->puedeAccederAdmin();
            
            default:
                return false;
        }
    }

    /**
     * Middleware para verificar permisos específicos
     */
    public function requierePermiso($permiso)
    {
        if (!$this->verificarPermiso($permiso)) {
            $this->flashSession->error('No tienes permisos para realizar esta acción');
            $this->response->redirect($this->request->getHTTPReferer() ?: '/');
            return false;
        }
        return true;
    }
}

