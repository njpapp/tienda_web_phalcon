<?php

namespace App\Controllers;

use App\Models\Categoria;

class CategoriasController extends BaseController
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
     * Lista de categorías
     */
    public function indexAction()
    {
        $this->view->setVar('title', 'Gestión de Categorías');
        
        // Obtener parámetros de búsqueda
        $busqueda = $this->request->getQuery('busqueda', 'string', '');
        $paginacion = $this->obtenerPaginacion();
        
        // Construir consulta
        $conditions = [];
        $bind = [];
        
        if (!empty($busqueda)) {
            $conditions[] = "(nombre ILIKE ?1 OR descripcion ILIKE ?1)";
            $bind[1] = "%{$busqueda}%";
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
        
        // Obtener categorías
        $categorias = Categoria::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'nombre ASC',
            'limit' => $paginacion['limit'],
            'offset' => $paginacion['offset']
        ]);
        
        // Contar total para paginación
        $totalCategorias = Categoria::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        $this->view->setVar('categorias', $categorias);
        $this->view->setVar('totalCategorias', $totalCategorias);
        $this->view->setVar('paginacion', $paginacion);
        $this->view->setVar('busqueda', $busqueda);
    }

    /**
     * Crear nueva categoría
     */
    public function crearAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        $this->view->setVar('title', 'Crear Categoría');
    }

    /**
     * Guardar nueva categoría
     */
    public function guardarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/categorias');
        }

        $datos = [
            'nombre' => $this->request->getPost('nombre', 'string'),
            'descripcion' => $this->request->getPost('descripcion', 'string'),
            'imagen' => $this->request->getPost('imagen', 'string')
        ];

        // Validar datos
        $errores = $this->validarCategoria($datos);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            
            return $this->dispatcher->forward([
                'action' => 'crear'
            ]);
        }

        // Crear categoría
        $categoria = new Categoria();
        $categoria->nombre = $datos['nombre'];
        $categoria->descripcion = $datos['descripcion'];
        $categoria->imagen = $datos['imagen'];

        if ($categoria->save()) {
            $this->flashSession->success('Categoría creada exitosamente');
            $this->registrarActividad('create_category', "Categoría creada: {$categoria->nombre}", ['categoria_id' => $categoria->id]);
            return $this->response->redirect('/admin/categorias');
        } else {
            $this->flashSession->error('Error al crear la categoría');
            $errores = [];
            foreach ($categoria->getMessages() as $message) {
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
     * Editar categoría existente
     */
    public function editarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        $id = $this->dispatcher->getParam('id');
        $categoria = Categoria::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$categoria) {
            $this->flashSession->error('Categoría no encontrada');
            return $this->response->redirect('/admin/categorias');
        }

        $this->view->setVar('title', 'Editar Categoría');
        $this->view->setVar('categoria', $categoria);
    }

    /**
     * Actualizar categoría existente
     */
    public function actualizarAction()
    {
        if (!$this->requirePermiso('gestionar_productos')) {
            return false;
        }
        
        if (!$this->request->isPost()) {
            return $this->response->redirect('/admin/categorias');
        }

        $id = $this->dispatcher->getParam('id');
        $categoria = Categoria::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$categoria) {
            $this->flashSession->error('Categoría no encontrada');
            return $this->response->redirect('/admin/categorias');
        }

        $datos = [
            'nombre' => $this->request->getPost('nombre', 'string'),
            'descripcion' => $this->request->getPost('descripcion', 'string'),
            'imagen' => $this->request->getPost('imagen', 'string'),
            'activo' => $this->request->getPost('activo', 'int', 1)
        ];

        // Validar datos (sin validar nombre duplicado para la misma categoría)
        $errores = $this->validarCategoria($datos, false, $categoria->id);

        if (!empty($errores)) {
            $this->flashSession->error('Por favor corrige los errores en el formulario');
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('categoria', $categoria);
            
            return $this->dispatcher->forward([
                'action' => 'editar'
            ]);
        }

        // Actualizar categoría
        $categoria->nombre = $datos['nombre'];
        $categoria->descripcion = $datos['descripcion'];
        $categoria->imagen = $datos['imagen'];
        $categoria->activo = $datos['activo'];

        if ($categoria->save()) {
            $this->flashSession->success('Categoría actualizada exitosamente');
            $this->registrarActividad('update_category', "Categoría actualizada: {$categoria->nombre}", ['categoria_id' => $categoria->id]);
            return $this->response->redirect('/admin/categorias');
        } else {
            $this->flashSession->error('Error al actualizar la categoría');
            $errores = [];
            foreach ($categoria->getMessages() as $message) {
                $errores[] = $message->getMessage();
            }
            $this->view->setVar('errores', $errores);
            $this->view->setVar('datos', $datos);
            $this->view->setVar('categoria', $categoria);
            
            return $this->dispatcher->forward([
                'action' => 'editar'
            ]);
        }
    }

    /**
     * Eliminar categoría (desactivar)
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
        
        $categoria = Categoria::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$categoria) {
            return $this->jsonError('Categoría no encontrada', 404);
        }

        // Verificar si la categoría tiene productos asociados
        $tieneProductos = $categoria->contarProductos() > 0;
        
        if ($tieneProductos) {
            // Solo desactivar si tiene productos
            $categoria->activo = false;
            if ($categoria->save()) {
                $this->registrarActividad('deactivate_category', "Categoría desactivada: {$categoria->nombre}");
                return $this->jsonSuccess('Categoría desactivada exitosamente');
            } else {
                return $this->jsonError('Error al desactivar la categoría');
            }
        } else {
            // Eliminar completamente si no tiene productos
            if ($categoria->delete()) {
                $this->registrarActividad('delete_category', "Categoría eliminada: {$categoria->nombre}");
                return $this->jsonSuccess('Categoría eliminada exitosamente');
            } else {
                return $this->jsonError('Error al eliminar la categoría');
            }
        }
    }

    /**
     * Ver detalle de la categoría
     */
    public function verAction()
    {
        $id = $this->dispatcher->getParam('id');
        $categoria = Categoria::findFirst([
            'conditions' => 'id = ?',
            'bind' => [$id]
        ]);

        if (!$categoria) {
            $this->flashSession->error('Categoría no encontrada');
            return $this->response->redirect('/admin/categorias');
        }

        $this->view->setVar('title', 'Detalle de Categoría: ' . $categoria->nombre);
        $this->view->setVar('categoria', $categoria);
        
        // Obtener productos de la categoría
        $productos = $categoria->getProductos([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC',
            'limit' => 20
        ]);
        $this->view->setVar('productos', $productos);
        $this->view->setVar('totalProductos', $categoria->contarProductos());
    }

    /**
     * Valida los datos de una categoría
     */
    private function validarCategoria($datos, $requiereNombre = true, $categoriaId = null)
    {
        $errores = [];

        // Validar nombre
        if ($requiereNombre) {
            if (empty($datos['nombre'])) {
                $errores['nombre'] = 'El nombre de la categoría es requerido';
            } else {
                // Verificar si el nombre ya existe (excluyendo la categoría actual en edición)
                $conditions = 'nombre = ?';
                $bind = [$datos['nombre']];
                
                if ($categoriaId) {
                    $conditions .= ' AND id != ?';
                    $bind[] = $categoriaId;
                }
                
                $categoriaExistente = Categoria::findFirst([
                    'conditions' => $conditions,
                    'bind' => $bind
                ]);
                
                if ($categoriaExistente) {
                    $errores['nombre'] = 'Ya existe una categoría con este nombre';
                }
            }
        }

        return $errores;
    }
}

