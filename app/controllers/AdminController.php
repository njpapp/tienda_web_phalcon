<?php

namespace App\Controllers;

use App\Models\Usuario;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\InventarioMovimiento;

class AdminController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Verificar que el usuario puede acceder al panel administrativo
        if (!$this->requireAdminAccess()) {
            return false;
        }
    }

    /**
     * Dashboard principal del administrador
     */
    public function dashboardAction()
    {
        $this->view->setVar('title', 'Panel de Administración');
        
        // Obtener estadísticas generales
        $estadisticas = $this->obtenerEstadisticasGenerales();
        $this->view->setVar('estadisticas', $estadisticas);
        
        // Obtener ventas recientes
        $ventasRecientes = $this->obtenerVentasRecientes();
        $this->view->setVar('ventasRecientes', $ventasRecientes);
        
        // Obtener productos con stock bajo
        $productosStockBajo = $this->obtenerProductosStockBajo();
        $this->view->setVar('productosStockBajo', $productosStockBajo);
        
        // Obtener actividad reciente
        $actividadReciente = $this->obtenerActividadReciente();
        $this->view->setVar('actividadReciente', $actividadReciente);
        
        $this->registrarActividad('dashboard_access', 'Acceso al dashboard administrativo');
    }

    /**
     * Lista de usuarios
     */
    public function usuariosAction()
    {
        $this->view->setVar('title', 'Gestión de Usuarios');
        
        // Obtener parámetros de búsqueda y paginación
        $busqueda = $this->request->getQuery('busqueda', 'string', '');
        $rol = $this->request->getQuery('rol', 'int', 0);
        $paginacion = $this->obtenerPaginacion();
        
        // Construir consulta
        $conditions = ['activo = true'];
        $bind = [];
        
        if (!empty($busqueda)) {
            $conditions[] = "(nombre ILIKE ?1 OR apellido ILIKE ?1 OR email ILIKE ?1)";
            $bind[1] = "%{$busqueda}%";
        }
        
        if ($rol > 0) {
            $conditions[] = "rol_id = ?2";
            $bind[2] = $rol;
        }
        
        // Obtener usuarios
        $usuarios = Usuario::find([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'created_at DESC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalUsuarios = Usuario::count([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind
        ]);
        
        $this->view->setVar('usuarios', $usuarios);
        $this->view->setVar('totalUsuarios', $totalUsuarios);
        $this->view->setVar('paginacion', $paginacion);
        $this->view->setVar('busqueda', $busqueda);
        $this->view->setVar('rolSeleccionado', $rol);
        
        // Obtener roles para el filtro
        $roles = \App\Models\Rol::obtenerActivos();
        $this->view->setVar('roles', $roles);
    }

    /**
     * Crear nuevo usuario
     */
    public function crearUsuarioAction()
    {
        if (!$this->requirePermiso('crear_usuario')) {
            return false;
        }
        
        $this->view->setVar('title', 'Crear Usuario');
        
        // Obtener roles disponibles
        $roles = \App\Models\Rol::obtenerActivos();
        $this->view->setVar('roles', $roles);
    }

    /**
     * Guardar nuevo usuario
     */
    public function guardarUsuarioAction()
    {
        if (!$this->requirePermiso('crear_usuario')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/usuarios');
        }

        $datos = [
            'email' => $this->request->getPost('email', 'email'),
            'password' => $this->request->getPost('password', 'string'),
            'nombre' => $this->request->getPost('nombre', 'string'),
            'apellido' => $this->request->getPost('apellido', 'string'),
            'telefono' => $this->request->getPost('telefono', 'string'),
            'direccion' => $this->request->getPost('direccion', 'string'),
            'ciudad' => $this->request->getPost('ciudad', 'string'),
            'codigo_postal' => $this->request->getPost('codigo_postal', 'string'),
            'pais' => $this->request->getPost('pais', 'string', 'México'),
            'rol_id' => $this->request->getPost('rol_id', 'int')
        ];

        // Validar datos
        $errores = $this->validarUsuario($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            $roles = \App\Models\Rol::obtenerActivos();
            $this->view->setVar('roles', $roles);
            
            return $this->dispatcher->forward([
                'action' => 'crearUsuario'
            ]);
        }

        // Crear usuario
        $usuario = new Usuario();
        $usuario->email = $datos['email'];
        $usuario->nombre = $datos['nombre'];
        $usuario->apellido = $datos['apellido'];
        $usuario->telefono = $datos['telefono'];
        $usuario->direccion = $datos['direccion'];
        $usuario->ciudad = $datos['ciudad'];
        $usuario->codigo_postal = $datos['codigo_postal'];
        $usuario->pais = $datos['pais'];
        $usuario->rol_id = $datos['rol_id'];
        $usuario->setPassword($datos['password']);
        $usuario->email_verificado = true; // Los usuarios creados por admin están verificados

        if ($usuario->save()) {
            $this->flashSession->success('Usuario creado exitosamente');
            $this->registrarActividad('create_user', "Usuario creado: {$usuario->email}", ['usuario_id' => $usuario->id]);
            return $this->response->redirect('/admin/usuarios');
        } else {
            $this->flashSession->error('Error al crear el usuario');
            $errores = [];
            foreach ($usuario->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            $roles = \App\Models\Rol::obtenerActivos();
            $this->view->setVar('roles', $roles);
            
            return $this->dispatcher->forward([
                'action' => 'crearUsuario'
            ]);
        }
    }

    /**
     * Editar usuario existente
     */
    public function editarUsuarioAction()
    {
        if (!$this->requirePermiso('editar_usuario')) {
            return false;
        }
        
        $id = $this->dispatcher->getParam('id');
        $usuario = Usuario::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$usuario) {
            $this->flashSession->error('Usuario no encontrado');
            return $this->response->redirect('/admin/usuarios');
        }

        $this->view->setVar('title', 'Editar Usuario');
        $this->view->setVar('usuario', $usuario);
        
        // Obtener roles disponibles
        $roles = \App\Models\Rol::obtenerActivos();
        $this->view->setVar('roles', $roles);
    }

    /**
     * Actualizar usuario existente
     */
    public function actualizarUsuarioAction()
    {
        if (!$this->requirePermiso('editar_usuario')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/usuarios');
        }

        $id = $this->dispatcher->getParam('id');
        $usuario = Usuario::findFirst([
            'conditions' => 'id = ? AND activo = true',
            'bind' => [$id]
        ]);

        if (!$usuario) {
            $this->flashSession->error('Usuario no encontrado');
            return $this->response->redirect('/admin/usuarios');
        }

        $datos = [
            'email' => $this->request->getPost('email', 'email'),
            'nombre' => $this->request->getPost('nombre', 'string'),
            'apellido' => $this->request->getPost('apellido', 'string'),
            'telefono' => $this->request->getPost('telefono', 'string'),
            'direccion' => $this->request->getPost('direccion', 'string'),
            'ciudad' => $this->request->getPost('ciudad', 'string'),
            'codigo_postal' => $this->request->getPost('codigo_postal', 'string'),
            'pais' => $this->request->getPost('pais', 'string'),
            'rol_id' => $this->request->getPost('rol_id', 'int'),
            'activo' => $this->request->getPost('activo', 'int', 1)
        ];

        // Validar datos (sin contraseña para edición)
        $errores = $this->validarUsuario($datos, false, $usuario->id);

        // Verificar si se quiere cambiar la contraseña
        $nuevaPassword = $this->request->getPost('nueva_password', 'string');
        if (!empty($nuevaPassword)) {
            if (strlen($nuevaPassword) < 6) {
                $errores['nueva_password'] = 'La nueva contraseña debe tener al menos 6 caracteres';
            }
        }

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('usuario', $usuario);
            
            $roles = \App\Models\Rol::obtenerActivos();
            $this->view->setVar('roles', $roles);
            
            return $this->dispatcher->forward([
                'action' => 'editarUsuario'
            ]);
        }

        // Actualizar usuario
        $usuario->email = $datos['email'];
        $usuario->nombre = $datos['nombre'];
        $usuario->apellido = $datos['apellido'];
        $usuario->telefono = $datos['telefono'];
        $usuario->direccion = $datos['direccion'];
        $usuario->ciudad = $datos['ciudad'];
        $usuario->codigo_postal = $datos['codigo_postal'];
        $usuario->pais = $datos['pais'];
        $usuario->rol_id = $datos['rol_id'];
        $usuario->activo = $datos['activo'];

        // Cambiar contraseña si se proporcionó
        if (!empty($nuevaPassword)) {
            $usuario->setPassword($nuevaPassword);
        }

        if ($usuario->save()) {
            $this->flashSession->success('Usuario actualizado exitosamente');
            $this->registrarActividad('update_user', "Usuario actualizado: {$usuario->email}", ['usuario_id' => $usuario->id]);
            return $this->response->redirect('/admin/usuarios');
        } else {
            $this->flashSession->error('Error al actualizar el usuario');
            $errores = [];
            foreach ($usuario->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('usuario', $usuario);
            
            $roles = \App\Models\Rol::obtenerActivos();
            $this->view->setVar('roles', $roles);
            
            return $this->dispatcher->forward([
                'action' => 'editarUsuario'
            ]);
        }
    }

    /**
     * Obtiene estadísticas generales del sistema
     */
    private function obtenerEstadisticasGenerales()
    {
        $db = $this->getDI()->getDb();
        
        // Total de usuarios por rol
        $totalClientes = Usuario::count(['conditions' => 'rol_id = 3 AND activo = true']);
        $totalEmpleados = Usuario::count(['conditions' => 'rol_id = 2 AND activo = true']);
        $totalAdmins = Usuario::count(['conditions' => 'rol_id = 1 AND activo = true']);
        
        // Total de productos
        $totalProductos = Producto::count(['conditions' => 'activo = true']);
        
        // Productos con stock bajo
        $productosStockBajo = Producto::count(['conditions' => 'stock_actual <= stock_minimo AND activo = true']);
        
        // Ventas del mes actual
        $ventasMes = $db->fetchOne(
            "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
             FROM pedidos 
             WHERE DATE_TRUNC('month', created_at) = DATE_TRUNC('month', CURRENT_DATE)
             AND estado_id != (SELECT id FROM estados_pedido WHERE nombre = 'cancelado')"
        );
        
        // Ventas del día
        $ventasHoy = $db->fetchOne(
            "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
             FROM pedidos 
             WHERE DATE(created_at) = CURRENT_DATE
             AND estado_id != (SELECT id FROM estados_pedido WHERE nombre = 'cancelado')"
        );

        return [
            'total_clientes' => $totalClientes,
            'total_empleados' => $totalEmpleados,
            'total_admins' => $totalAdmins,
            'total_productos' => $totalProductos,
            'productos_stock_bajo' => $productosStockBajo,
            'ventas_mes_total' => $ventasMes['total'],
            'ventas_mes_monto' => $ventasMes['monto'],
            'ventas_hoy_total' => $ventasHoy['total'],
            'ventas_hoy_monto' => $ventasHoy['monto']
        ];
    }

    /**
     * Obtiene las ventas más recientes
     */
    private function obtenerVentasRecientes($limite = 10)
    {
        return Pedido::find([
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene productos con stock bajo
     */
    private function obtenerProductosStockBajo($limite = 10)
    {
        return Producto::find([
            'conditions' => 'stock_actual <= stock_minimo AND activo = true',
            'order' => 'stock_actual ASC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene actividad reciente del sistema
     */
    private function obtenerActividadReciente($limite = 10)
    {
        return InventarioMovimiento::find([
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Valida los datos de un usuario
     */
    private function validarUsuario($datos, $requierePassword = true, $usuarioId = null)
    {
        $errores = [];

        // Validar email
        if (empty($datos['email'])) {
            $errores['email'] = 'El email es requerido';
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'El email debe tener un formato válido';
        } else {
            // Verificar si el email ya existe (excluyendo el usuario actual en edición)
            $conditions = 'email = ?';
            $bind = [$datos['email']];
            
            if ($usuarioId) {
                $conditions .= ' AND id != ?';
                $bind[] = $usuarioId;
            }
            
            $usuarioExistente = Usuario::findFirst([
                'conditions' => $conditions,
                'bind' => $bind
            ]);
            
            if ($usuarioExistente) {
                $errores['email'] = 'Este email ya está registrado';
            }
        }

        // Validar contraseña (solo si es requerida)
        if ($requierePassword) {
            if (empty($datos['password'])) {
                $errores['password'] = 'La contraseña es requerida';
            } elseif (strlen($datos['password']) < 6) {
                $errores['password'] = 'La contraseña debe tener al menos 6 caracteres';
            }
        }

        // Validar nombre
        if (empty($datos['nombre'])) {
            $errores['nombre'] = 'El nombre es requerido';
        }

        // Validar apellido
        if (empty($datos['apellido'])) {
            $errores['apellido'] = 'El apellido es requerido';
        }

        // Validar rol
        if (empty($datos['rol_id']) || $datos['rol_id'] <= 0) {
            $errores['rol_id'] = 'Debe seleccionar un rol válido';
        }

        return $errores;
    }
}

