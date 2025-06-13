<?php

namespace App\Controllers;

use App\Models\Usuario;
use App\Models\Pedido;
use App\Models\TarjetaCliente;
use App\Models\Carrito;

class ClienteController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Verificar que el usuario es cliente
        if (!$this->requireCliente()) {
            return false;
        }
    }

    /**
     * Dashboard del cliente
     */
    public function dashboardAction()
    {
        $this->view->setVar('title', 'Mi Cuenta');
        
        // Obtener estadísticas del cliente
        $estadisticas = $this->usuario->getEstadisticas();
        $this->view->setVar('estadisticas', $estadisticas);
        
        // Obtener pedidos recientes
        $pedidosRecientes = $this->usuario->getPedidosRecientes(5);
        $this->view->setVar('pedidosRecientes', $pedidosRecientes);
        
        // Obtener items en el carrito
        $itemsCarrito = Carrito::obtenerPorCliente($this->usuario->id);
        $this->view->setVar('itemsCarrito', $itemsCarrito);
        $this->view->setVar('totalCarrito', Carrito::calcularTotal($this->usuario->id));
        
        // Obtener tarjetas del cliente
        $tarjetas = TarjetaCliente::obtenerPorCliente($this->usuario->id);
        $this->view->setVar('tarjetas', $tarjetas);
        
        $this->registrarActividad('client_dashboard', 'Acceso al dashboard del cliente');
    }

    /**
     * Perfil del cliente
     */
    public function perfilAction()
    {
        $this->view->setVar('title', 'Mi Perfil');
        $this->view->setVar('usuario', $this->usuario);
    }

    /**
     * Actualizar perfil del cliente
     */
    public function actualizarPerfilAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/cliente/perfil');
        }

        $datos = [
            'nombre' => $this->request->getPost('nombre', 'string'),
            'apellido' => $this->request->getPost('apellido', 'string'),
            'telefono' => $this->request->getPost('telefono', 'string'),
            'direccion' => $this->request->getPost('direccion', 'string'),
            'ciudad' => $this->request->getPost('ciudad', 'string'),
            'codigo_postal' => $this->request->getPost('codigo_postal', 'string'),
            'pais' => $this->request->getPost('pais', 'string')
        ];

        // Validar datos
        $errores = $this->validarPerfilCliente($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            return $this->dispatcher->forward([
                'action' => 'perfil'
            ]);
        }

        // Actualizar usuario
        $this->usuario->nombre = $datos['nombre'];
        $this->usuario->apellido = $datos['apellido'];
        $this->usuario->telefono = $datos['telefono'];
        $this->usuario->direccion = $datos['direccion'];
        $this->usuario->ciudad = $datos['ciudad'];
        $this->usuario->codigo_postal = $datos['codigo_postal'];
        $this->usuario->pais = $datos['pais'];

        if ($this->usuario->save()) {
            $this->flashSession->success('Perfil actualizado exitosamente');
            $this->registrarActividad('update_profile', 'Cliente actualizó su perfil');
            return $this->response->redirect('/cliente/perfil');
        } else {
            $this->flashSession->error('Error al actualizar el perfil');
            $errores = [];
            foreach ($this->usuario->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            return $this->dispatcher->forward([
                'action' => 'perfil'
            ]);
        }
    }

    /**
     * Historial de compras del cliente
     */
    public function comprasAction()
    {
        $this->view->setVar('title', 'Mis Compras');
        
        // Obtener parámetros de paginación
        $paginacion = $this->obtenerPaginacion();
        
        // Obtener pedidos del cliente
        $pedidos = Pedido::find([
            'conditions' => 'cliente_id = ?',
            'bind' => [$this->usuario->id],
            'order' => 'created_at DESC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalPedidos = Pedido::count([
            'conditions' => 'cliente_id = ?',
            'bind' => [$this->usuario->id]
        ]);
        
        $this->view->setVar('pedidos', $pedidos);
        $this->view->setVar('totalPedidos', $totalPedidos);
        $this->view->setVar('paginacion', $paginacion);
    }

    /**
     * Detalle de una compra específica
     */
    public function detalleCompraAction()
    {
        $id = $this->dispatcher->getParam('id');
        
        $pedido = Pedido::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$id, $this->usuario->id]
        ]);

        if (!$pedido) {
            $this->flashSession->error('Pedido no encontrado');
            return $this->response->redirect('/cliente/compras');
        }

        $this->view->setVar('title', 'Detalle del Pedido #' . $pedido->numero_pedido);
        $this->view->setVar('pedido', $pedido);
        
        $this->registrarActividad('view_order_detail', "Cliente vio detalle del pedido {$pedido->numero_pedido}");
    }

    /**
     * Gestión de tarjetas del cliente
     */
    public function tarjetasAction()
    {
        $this->view->setVar('title', 'Mis Tarjetas');
        
        $tarjetas = TarjetaCliente::obtenerPorCliente($this->usuario->id);
        $this->view->setVar('tarjetas', $tarjetas);
    }

    /**
     * Agregar nueva tarjeta
     */
    public function agregarTarjetaAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/cliente/tarjetas');
        }

        $datos = [
            'tipo' => $this->request->getPost('tipo', 'string'),
            'marca' => $this->request->getPost('marca', 'string'),
            'numero' => $this->request->getPost('numero', 'string'),
            'nombre_titular' => $this->request->getPost('nombre_titular', 'string'),
            'mes_expiracion' => $this->request->getPost('mes_expiracion', 'int'),
            'año_expiracion' => $this->request->getPost('año_expiracion', 'int'),
            'es_principal' => $this->request->getPost('es_principal', 'int', 0)
        ];

        // Validar datos
        $errores = $this->validarTarjeta($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            return $this->dispatcher->forward([
                'action' => 'tarjetas'
            ]);
        }

        // Crear tarjeta
        $tarjeta = new TarjetaCliente();
        $tarjeta->cliente_id = $this->usuario->id;
        $tarjeta->tipo = $datos['tipo'];
        $tarjeta->marca = $datos['marca'];
        $tarjeta->ultimos_4_digitos = substr($datos['numero'], -4);
        $tarjeta->nombre_titular = strtoupper($datos['nombre_titular']);
        $tarjeta->mes_expiracion = $datos['mes_expiracion'];
        $tarjeta->año_expiracion = $datos['año_expiracion'];
        $tarjeta->es_principal = $datos['es_principal'];

        if ($tarjeta->save()) {
            $this->flashSession->success('Tarjeta agregada exitosamente');
            $this->registrarActividad('add_card', 'Cliente agregó nueva tarjeta');
            return $this->response->redirect('/cliente/tarjetas');
        } else {
            $this->flashSession->error('Error al agregar la tarjeta');
            $errores = [];
            foreach ($tarjeta->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            return $this->dispatcher->forward([
                'action' => 'tarjetas'
            ]);
        }
    }

    /**
     * Eliminar tarjeta
     */
    public function eliminarTarjetaAction()
    {
        if (!$this->request->isDelete() && !$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $id = $this->dispatcher->getParam('id');
        
        $tarjeta = TarjetaCliente::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$id, $this->usuario->id]
        ]);

        if (!$tarjeta) {
            return $this->jsonError('Tarjeta no encontrada', 404);
        }

        if ($tarjeta->delete()) {
            $this->registrarActividad('delete_card', 'Cliente eliminó tarjeta');
            return $this->jsonSuccess('Tarjeta eliminada exitosamente');
        } else {
            return $this->jsonError('Error al eliminar la tarjeta');
        }
    }

    /**
     * Establecer tarjeta como principal
     */
    public function establecerTarjetaPrincipalAction()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $id = $this->dispatcher->getParam('id');
        
        $tarjeta = TarjetaCliente::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$id, $this->usuario->id]
        ]);

        if (!$tarjeta) {
            return $this->jsonError('Tarjeta no encontrada', 404);
        }

        // Desmarcar todas las tarjetas como principales
        TarjetaCliente::find([
            'conditions' => 'cliente_id = ?',
            'bind' => [$this->usuario->id]
        ])->update(['es_principal' => false]);

        // Marcar esta tarjeta como principal
        $tarjeta->es_principal = true;

        if ($tarjeta->save()) {
            $this->registrarActividad('set_primary_card', 'Cliente estableció tarjeta principal');
            return $this->jsonSuccess('Tarjeta establecida como principal');
        } else {
            return $this->jsonError('Error al establecer la tarjeta como principal');
        }
    }

    /**
     * Configuración de la cuenta
     */
    public function configuracionAction()
    {
        $this->view->setVar('title', 'Configuración de Cuenta');
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPasswordAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect('/cliente/configuracion');
        }

        $passwordActual = $this->request->getPost('password_actual', 'string');
        $passwordNuevo = $this->request->getPost('password_nuevo', 'string');
        $passwordConfirm = $this->request->getPost('password_confirm', 'string');

        // Validar datos
        $errores = [];

        if (empty($passwordActual)) {
            $errores['password_actual'] = 'La contraseña actual es requerida';
        } elseif (!$this->usuario->verificarPassword($passwordActual)) {
            $errores['password_actual'] = 'La contraseña actual es incorrecta';
        }

        if (empty($passwordNuevo)) {
            $errores['password_nuevo'] = 'La nueva contraseña es requerida';
        } elseif (strlen($passwordNuevo) < 6) {
            $errores['password_nuevo'] = 'La nueva contraseña debe tener al menos 6 caracteres';
        }

        if ($passwordNuevo !== $passwordConfirm) {
            $errores['password_confirm'] = 'Las contraseñas no coinciden';
        }

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            return $this->dispatcher->forward([
                'action' => 'configuracion'
            ]);
        }

        // Cambiar contraseña
        $this->usuario->setPassword($passwordNuevo);

        if ($this->usuario->save()) {
            $this->flashSession->success('Contraseña actualizada exitosamente');
            $this->registrarActividad('change_password', 'Cliente cambió su contraseña');
            return $this->response->redirect('/cliente/configuracion');
        } else {
            $this->flashSession->error('Error al actualizar la contraseña');
            return $this->dispatcher->forward([
                'action' => 'configuracion'
            ]);
        }
    }

    /**
     * Valida los datos del perfil del cliente
     */
    private function validarPerfilCliente($datos)
    {
        $errores = [];

        if (empty($datos['nombre'])) {
            $errores['nombre'] = 'El nombre es requerido';
        } elseif (strlen($datos['nombre']) < 2) {
            $errores['nombre'] = 'El nombre debe tener al menos 2 caracteres';
        }

        if (empty($datos['apellido'])) {
            $errores['apellido'] = 'El apellido es requerido';
        } elseif (strlen($datos['apellido']) < 2) {
            $errores['apellido'] = 'El apellido debe tener al menos 2 caracteres';
        }

        if (!empty($datos['telefono']) && !preg_match('/^[\d\s\-\+\(\)]+$/', $datos['telefono'])) {
            $errores['telefono'] = 'El teléfono tiene un formato inválido';
        }

        return $errores;
    }

    /**
     * Valida los datos de una tarjeta
     */
    private function validarTarjeta($datos)
    {
        $errores = [];

        if (empty($datos['tipo'])) {
            $errores['tipo'] = 'El tipo de tarjeta es requerido';
        } elseif (!in_array($datos['tipo'], ['credito', 'debito'])) {
            $errores['tipo'] = 'Tipo de tarjeta inválido';
        }

        if (empty($datos['marca'])) {
            $errores['marca'] = 'La marca de la tarjeta es requerida';
        }

        if (empty($datos['numero'])) {
            $errores['numero'] = 'El número de tarjeta es requerido';
        } elseif (!preg_match('/^\d{13,19}$/', str_replace([' ', '-'], '', $datos['numero']))) {
            $errores['numero'] = 'El número de tarjeta debe tener entre 13 y 19 dígitos';
        }

        if (empty($datos['nombre_titular'])) {
            $errores['nombre_titular'] = 'El nombre del titular es requerido';
        }

        if (empty($datos['mes_expiracion']) || $datos['mes_expiracion'] < 1 || $datos['mes_expiracion'] > 12) {
            $errores['mes_expiracion'] = 'Mes de expiración inválido';
        }

        if (empty($datos['año_expiracion']) || $datos['año_expiracion'] < date('Y')) {
            $errores['año_expiracion'] = 'Año de expiración inválido';
        }

        // Verificar si la tarjeta ya expiró
        if (!empty($datos['mes_expiracion']) && !empty($datos['año_expiracion'])) {
            $fechaExpiracion = mktime(0, 0, 0, $datos['mes_expiracion'] + 1, 0, $datos['año_expiracion']);
            if (time() > $fechaExpiracion) {
                $errores['expiracion'] = 'La tarjeta ya ha expirado';
            }
        }

        return $errores;
    }
}

