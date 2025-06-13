<?php

namespace App\Controllers;

use App\Models\Usuario;

class AuthController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Si ya está autenticado y trata de acceder a login/register, redirigir
        if ($this->auth->estaAutenticado() && 
            in_array($this->dispatcher->getActionName(), ['login', 'register'])) {
            $this->redirigirSegunRol();
            return false;
        }
    }

    /**
     * Muestra el formulario de login
     */
    public function loginAction()
    {
        $this->view->setVar('title', 'Iniciar Sesión');
    }

    /**
     * Procesa el login del usuario
     */
    public function authenticateAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/login');
        }

        $email = $this->request->getPost('email', 'email');
        $password = $this->request->getPost('password', 'string');
        $recordar = $this->request->getPost('recordar', 'int', 0);

        // Validar datos
        $errores = $this->validarDatos([
            'email' => $email,
            'password' => $password
        ], [
            'email' => [
                'required' => true,
                'email' => true,
                'message' => 'El email es requerido y debe ser válido'
            ],
            'password' => [
                'required' => true,
                'min_length' => 6,
                'message' => 'La contraseña es requerida'
            ]
        ]);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('email', $email);
            return $this->dispatcher->forward([
                'action' => 'login'
            ]);
        }

        // Intentar autenticar
        $resultado = $this->auth->login($email, $password, $recordar);

        if ($resultado['success']) {
            $this->flashSession->success($resultado['message']);
            $this->registrarActividad('login', 'Usuario inició sesión');
            return $this->redirigirSegunRol();
        } else {
            $this->flashSession->error($resultado['message']);
            $this->view->setVar('email', $email);
            return $this->dispatcher->forward([
                'action' => 'login'
            ]);
        }
    }

    /**
     * Muestra el formulario de registro
     */
    public function registerAction()
    {
        $this->view->setVar('title', 'Registrarse');
    }

    /**
     * Procesa el registro de un nuevo usuario
     */
    public function createAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/register');
        }

        $datos = [
            'email' => $this->request->getPost('email', 'email'),
            'password' => $this->request->getPost('password', 'string'),
            'password_confirm' => $this->request->getPost('password_confirm', 'string'),
            'nombre' => $this->request->getPost('nombre', 'string'),
            'apellido' => $this->request->getPost('apellido', 'string'),
            'telefono' => $this->request->getPost('telefono', 'string'),
            'direccion' => $this->request->getPost('direccion', 'string'),
            'ciudad' => $this->request->getPost('ciudad', 'string'),
            'codigo_postal' => $this->request->getPost('codigo_postal', 'string'),
            'pais' => $this->request->getPost('pais', 'string', 'Colombia')
        ];

        // Validar datos
        $errores = $this->validarRegistro($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            return $this->dispatcher->forward([
                'action' => 'register'
            ]);
        }

        // Intentar registrar
        $resultado = $this->auth->registrar($datos);

        if ($resultado['success']) {
            $this->flashSession->success('Registro exitoso. Ya puedes iniciar sesión.');
            return $this->response->redirect('/login');
        } else {
            $this->flashSession->error($resultado['message']);
            
            // Si hay errores de validación del modelo
            if (isset($resultado['errors'])) {
                $modelErrors = [];
                foreach ($resultado['errors'] as $error) {
                    $modelErrors[] = $error->getMessage();
                }
                $this->view->setVar('modelErrors', $modelErrors);
            }
            
            $this->view->setVar('datos', $datos);
            return $this->dispatcher->forward([
                'action' => 'register'
            ]);
        }
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logoutAction()
    {
        if ($this->auth->estaAutenticado()) {
            $this->registrarActividad('logout', 'Usuario cerró sesión');
            $this->auth->logout();
            $this->flashSession->success('Sesión cerrada exitosamente');
        }
        
        return $this->response->redirect('/');
    }

    /**
     * Valida los datos de registro
     */
    private function validarRegistro($datos)
    {
        $errores = [];

        // Validar email
        if (empty($datos['email'])) {
            $errores['email'] = 'El email es requerido';
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'El email debe tener un formato válido';
        } else {
            // Verificar si el email ya existe
            $usuarioExistente = Usuario::findFirst([
                'conditions' => 'email = ?',
                'bind' => [$datos['email']]
            ]);
            if ($usuarioExistente) {
                $errores['email'] = 'Este email ya está registrado';
            }
        }

        // Validar contraseña
        if (empty($datos['password'])) {
            $errores['password'] = 'La contraseña es requerida';
        } elseif (strlen($datos['password']) < 6) {
            $errores['password'] = 'La contraseña debe tener al menos 6 caracteres';
        }

        // Validar confirmación de contraseña
        if ($datos['password'] !== $datos['password_confirm']) {
            $errores['password_confirm'] = 'Las contraseñas no coinciden';
        }

        // Validar nombre
        if (empty($datos['nombre'])) {
            $errores['nombre'] = 'El nombre es requerido';
        } elseif (strlen($datos['nombre']) < 2) {
            $errores['nombre'] = 'El nombre debe tener al menos 2 caracteres';
        }

        // Validar apellido
        if (empty($datos['apellido'])) {
            $errores['apellido'] = 'El apellido es requerido';
        } elseif (strlen($datos['apellido']) < 2) {
            $errores['apellido'] = 'El apellido debe tener al menos 2 caracteres';
        }

        // Validar teléfono (opcional pero si se proporciona debe ser válido)
        if (!empty($datos['telefono']) && !preg_match('/^[\d\s\-\+\(\)]+$/', $datos['telefono'])) {
            $errores['telefono'] = 'El teléfono tiene un formato inválido';
        }

        return $errores;
    }

    /**
     * Muestra el formulario para recuperar contraseña
     */
    public function forgotPasswordAction()
    {
        $this->view->setVar('title', 'Recuperar Contraseña');
    }

    /**
     * Procesa la solicitud de recuperación de contraseña
     */
    public function sendResetAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/forgot-password');
        }

        $email = $this->request->getPost('email', 'email');

        if (empty($email)) {
            $this->flashSession->error('El email es requerido');
            return $this->dispatcher->forward([
                'action' => 'forgotPassword'
            ]);
        }

        $usuario = Usuario::findFirst([
            'conditions' => 'email = ? AND activo = true',
            'bind' => [$email]
        ]);

        // Siempre mostrar el mismo mensaje por seguridad
        $this->flashSession->success('Si el email existe en nuestro sistema, recibirás instrucciones para recuperar tu contraseña.');

        if ($usuario) {
            // Aquí implementarías el envío de email
            // Por ahora solo registramos la actividad
            $this->logger->info("Solicitud de recuperación de contraseña para: {$email}");
        }

        return $this->response->redirect('/login');
    }

    /**
     * Cambia la contraseña del usuario actual
     */
    public function changePasswordAction()
    {
        if (!$this->requireAuth()) {
            return false;
        }

        if ($this->request->isPost()) {
            $passwordActual = $this->request->getPost('password_actual', 'string');
            $passwordNuevo = $this->request->getPost('password_nuevo', 'string');
            $passwordConfirm = $this->request->getPost('password_confirm', 'string');

            // Validar datos
            $errores = [];

            if (empty($passwordActual)) {
                $errores['password_actual'] = 'La contraseña actual es requerida';
            }

            if (empty($passwordNuevo)) {
                $errores['password_nuevo'] = 'La nueva contraseña es requerida';
            } elseif (strlen($passwordNuevo) < 6) {
                $errores['password_nuevo'] = 'La nueva contraseña debe tener al menos 6 caracteres';
            }

            if ($passwordNuevo !== $passwordConfirm) {
                $errores['password_confirm'] = 'Las contraseñas no coinciden';
            }

            if (empty($errores)) {
                $resultado = $this->auth->cambiarPassword($passwordActual, $passwordNuevo);

                if ($resultado['success']) {
                    $this->flashSession->success($resultado['message']);
                    $this->registrarActividad('change_password', 'Usuario cambió su contraseña');
                    return $this->response->redirect('/cliente/perfil');
                } else {
                    $this->flashSession->error($resultado['message']);
                }
            } else {
                $this->view->setVar('errores', $errores);
            }
        }

        $this->view->setVar('title', 'Cambiar Contraseña');
    }
}

