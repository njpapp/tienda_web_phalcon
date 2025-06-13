<?php

namespace App\Controllers;

use App\Models\Proveedor;

class ProveedoresController extends BaseController
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
     * Lista de proveedores
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Gestión de Proveedores');
        
        // Obtener parámetros de búsqueda
        $busqueda = $this->request->getQuery('busqueda', 'string', '');
        $paginacion = $this->obtenerPaginacion();
        
        // Construir consulta
        $conditions = [];
        $bind = [];
        
        if (!empty($busqueda)) {
            $conditions[] = "(nombre ILIKE ?1 OR email ILIKE ?1 OR telefono ILIKE ?1)";
            $bind[1] = "%{$busqueda}%";
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
        
        // Obtener proveedores
        $proveedores = Proveedor::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'nombre ASC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalProveedores = Proveedor::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        $this->view->setVar('proveedores', $proveedores);
        $this->view->setVar('totalProveedores', $totalProveedores);
        $this->view->setVar('paginacion', $paginacion);
        $this->view->setVar('busqueda', $busqueda);
    }

    /**
     * Crear nuevo proveedor
     */
    public function crearAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        $this->view->setVar('title', 'Crear Proveedor');
    }

    /**
     * Guardar nuevo proveedor
     */
    public function guardarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/proveedores');
        }

        $datos = [
            'nombre' => $this->request->getPost('nombre', 'string'),
            'contacto' => $this->request->getPost('contacto', 'string'),
            'email' => $this->request->getPost('email', 'email'),
            'telefono' => $this->request->getPost('telefono', 'string'),
            'direccion' => $this->request->getPost('direccion', 'string'),
            'ciudad' => $this->request->getPost('ciudad', 'string'),
            'pais' => $this->request->getPost('pais', 'string', 'Colombia'),
            'sitio_web' => $this->request->getPost('sitio_web', 'string'),
            'notas' => $this->request->getPost('notas', 'string')
        ];

        // Validar datos
        $errores = $this->validarProveedor($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            return $this->dispatcher->forward([
                'action' => 'crear'
            ]);
        }

        // Crear proveedor
        $proveedor = new Proveedor();
        $proveedor->nombre = $datos['nombre'];
        $proveedor->contacto = $datos['contacto'];
        $proveedor->email = $datos['email'];
        $proveedor->telefono = $datos['telefono'];
        $proveedor->direccion = $datos['direccion'];
        $proveedor->ciudad = $datos['ciudad'];
        $proveedor->pais = $datos['pais'];
        $proveedor->sitio_web = $datos['sitio_web'];
        $proveedor->notas = $datos['notas'];

        if ($proveedor->save()) {
            $this->flashSession->success('Proveedor creado exitosamente');
            $this->registrarActividad('create_supplier', "Proveedor creado: {$proveedor->nombre}", ['proveedor_id' => $proveedor->id]);
            return $this->response->redirect('/admin/proveedores');
        } else {
            $this->flashSession->error('Error al crear el proveedor');
            $errores = [];
            foreach ($proveedor->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            return $this->dispatcher->forward([
                'action' => 'crear'
            ]);
        }
    }

    /**
     * Editar proveedor existente
     */
    public function editarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        $id = $this->dispatcher->getParam('id');
        $proveedor = Proveedor::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$proveedor) {
            $this->flashSession->error('Proveedor no encontrado');
            return $this->response->redirect('/admin/proveedores');
        }

        $this->view->setVar('title', 'Editar Proveedor');
        $this->view->setVar('proveedor', $proveedor);
    }

    /**
     * Actualizar proveedor existente
     */
    public function actualizarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/proveedores');
        }

        $id = $this->dispatcher->getParam('id');
        $proveedor = Proveedor::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$proveedor) {
            $this->flashSession->error('Proveedor no encontrado');
            return $this->response->redirect('/admin/proveedores');
        }

        $datos = [
            'nombre' => $this->request->getPost('nombre', 'string'),
            'contacto' => $this->request->getPost('contacto', 'string'),
            'email' => $this->request->getPost('email', 'email'),
            'telefono' => $this->request->getPost('telefono', 'string'),
            'direccion' => $this->request->getPost('direccion', 'string'),
            'ciudad' => $this->request->getPost('ciudad', 'string'),
            'pais' => $this->request->getPost('pais', 'string'),
            'sitio_web' => $this->request->getPost('sitio_web', 'string'),
            'notas' => $this->request->getPost('notas', 'string'),
            'activo' => $this->request->getPost('activo', 'int', 1)
        ];

        // Validar datos (sin validar email duplicado para el mismo proveedor)
        $errores = $this->validarProveedor($datos, false, $proveedor->id);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('proveedor', $proveedor);
            
            return $this->dispatcher->forward([
                'action' => 'editar'
            ]);
        }

        // Actualizar proveedor
        $proveedor->nombre = $datos['nombre'];
        $proveedor->contacto = $datos['contacto'];
        $proveedor->email = $datos['email'];
        $proveedor->telefono = $datos['telefono'];
        $proveedor->direccion = $datos['direccion'];
        $proveedor->ciudad = $datos['ciudad'];
        $proveedor->pais = $datos['pais'];
        $proveedor->sitio_web = $datos['sitio_web'];
        $proveedor->notas = $datos['notas'];
        $proveedor->activo = $datos['activo'];

        if ($proveedor->save()) {
            $this->flashSession->success('Proveedor actualizado exitosamente');
            $this->registrarActividad('update_supplier', "Proveedor actualizado: {$proveedor->nombre}", ['proveedor_id' => $proveedor->id]);
            return $this->response->redirect('/admin/proveedores');
        } else {
            $this->flashSession->error('Error al actualizar el proveedor');
            $errores = [];
            foreach ($proveedor->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('proveedor', $proveedor);
            
            return $this->dispatcher->forward([
                'action' => 'editar'
            ]);
        }
    }

    /**
     * Eliminar proveedor (desactivar)
     */
    public function eliminarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isDelete() && !$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $id = $this->dispatcher->getParam('id');
        
        $proveedor = Proveedor::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$proveedor) {
            return $this->jsonError('Proveedor no encontrado', 404);
        }

        // Verificar si el proveedor tiene productos asociados
        $tieneProductos = $proveedor->contarProductos() > 0;
        
        if ($tieneProductos) {
            // Solo desactivar si tiene productos
            $proveedor->activo = false;
            if ($proveedor->save()) {
                $this->registrarActividad('deactivate_supplier', "Proveedor desactivado: {$proveedor->nombre}");
                return $this->jsonSuccess('Proveedor desactivado exitosamente');
            } else {
                return $this->jsonError('Error al desactivar el proveedor');
            }
        } else {
            // Eliminar completamente si no tiene productos
            if ($proveedor->delete()) {
                $this->registrarActividad('delete_supplier', "Proveedor eliminado: {$proveedor->nombre}");
                return $this->jsonSuccess('Proveedor eliminado exitosamente');
            } else {
                return $this->jsonError('Error al eliminar el proveedor');
            }
        }
    }

    /**
     * Ver detalle del proveedor
     */
    public function verAction()
    {
        $id = $this->dispatcher->getParam('id');
        $proveedor = Proveedor::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$proveedor) {
            $this->flashSession->error('Proveedor no encontrado');
            return $this->response->redirect('/admin/proveedores');
        }

        $this->view->setVar('title', 'Detalle del Proveedor: ' . $proveedor->nombre);
        $this->view->setVar('proveedor', $proveedor);
        
        // Obtener productos del proveedor
        $productos = $proveedor->getProductos([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC',
            'limit' => 20
        ]);
        $this->view->setVar('productos', $productos);
        $this->view->setVar('totalProductos', $proveedor->contarProductos());
    }

    /**
     * Valida los datos de un proveedor
     */
    private function validarProveedor($datos, $requiereEmail = true, $proveedorId = null)
    {
        $errores = [];

        // Validar nombre
        if (empty($datos['nombre'])) {
            $errores['nombre'] = 'El nombre del proveedor es requerido';
        }

        // Validar email
        if ($requiereEmail && !empty($datos['email'])) {
            if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                $errores['email'] = 'El email no tiene un formato válido';
            } else {
                // Verificar si el email ya existe (excluyendo el proveedor actual en edición)
                $conditions = 'email = ?';
                $bind = [$datos['email']];
                
                if ($proveedorId) {
                    $conditions .= ' AND id != ?';
                    $bind[] = $proveedorId;
                }
                
                $proveedorExistente = Proveedor::findFirst([
                    'conditions' => $conditions,
                    'bind' => $bind
                ]);
                
                if ($proveedorExistente) {
                    $errores['email'] = 'Ya existe un proveedor con este email';
                }
            }
        }

        // Validar teléfono
        if (!empty($datos['telefono']) && !preg_match('/^[\d\s\-\+\(\)]+$/', $datos['telefono'])) {
            $errores['telefono'] = 'El teléfono tiene un formato inválido';
        }

        // Validar sitio web
        if (!empty($datos['sitio_web']) && !filter_var($datos['sitio_web'], FILTER_VALIDATE_URL)) {
            $errores['sitio_web'] = 'El sitio web no tiene un formato válido';
        }

        return $errores;
    }
}

