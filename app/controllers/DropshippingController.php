<?php

namespace App\Controllers;

use App\Models\ProveedorDropshipping;
use App\Models\ProductoExterno;
use App\Models\SyncHistory;
use App\Models\AlertaSistema;
use App\Models\ApiLog;
use App\Library\Dropshipping\SyncService;

/**
 * Controlador para la gestión de dropshipping en el panel administrativo
 */
class DropshippingController extends BaseController
{
    protected $rolesPermitidos = ['admin', 'empleado'];

    public function initialize()
    {
        parent::initialize();
        $this->view->setVar('seccion', 'dropshipping');
    }

    /**
     * Dashboard principal de dropshipping
     */
    public function indexAction()
    {
        // Estadísticas generales
        $stats = [
            'total_proveedores' => ProveedorDropshipping::count(),
            'proveedores_activos' => ProveedorDropshipping::count(['activo = true']),
            'total_productos_externos' => ProductoExterno::count(),
            'productos_disponibles' => ProductoExterno::count(['disponible = true']),
            'sincronizaciones_hoy' => SyncHistory::count([
                'created_at >= ?',
                'bind' => [date('Y-m-d 00:00:00')]
            ]),
            'alertas_no_leidas' => AlertaSistema::count(['leida = false'])
        ];

        // Últimas sincronizaciones
        $ultimasSincronizaciones = SyncHistory::getRecientes(10);

        // Alertas críticas recientes
        $alertasCriticas = AlertaSistema::getCriticas(24);

        // Proveedores con estadísticas
        $proveedores = ProveedorDropshipping::getProveedoresActivos();
        $proveedoresStats = [];
        
        foreach ($proveedores as $proveedor) {
            $proveedoresStats[] = [
                'proveedor' => $proveedor,
                'estadisticas' => $proveedor->getEstadisticas()
            ];
        }

        $this->view->setVars([
            'stats' => $stats,
            'ultimasSincronizaciones' => $ultimasSincronizaciones,
            'alertasCriticas' => $alertasCriticas,
            'proveedoresStats' => $proveedoresStats
        ]);
    }

    /**
     * Gestión de proveedores
     */
    public function proveedoresAction()
    {
        $proveedores = ProveedorDropshipping::find(['order' => 'nombre ASC']);
        
        $this->view->setVar('proveedores', $proveedores);
    }

    /**
     * Crear nuevo proveedor
     */
    public function crearProveedorAction()
    {
        if ($this->request->isPost()) {
            $proveedor = new ProveedorDropshipping();
            
            $proveedor->nombre = $this->request->getPost('nombre', 'string');
            $proveedor->tipo = $this->request->getPost('tipo', 'string');
            $proveedor->api_key = $this->request->getPost('api_key', 'string');
            $proveedor->api_secret = $this->request->getPost('api_secret', 'string');
            $proveedor->activo = $this->request->getPost('activo', 'int', 0) == 1;
            $proveedor->limite_requests_dia = $this->request->getPost('limite_requests_dia', 'int', 1000);
            
            // Configuración específica
            $configuracion = [
                'margen_defecto' => $this->request->getPost('margen_defecto', 'float', 30),
                'precio_minimo' => $this->request->getPost('precio_minimo', 'float', 1),
                'precio_maximo' => $this->request->getPost('precio_maximo', 'float', 10000),
                'auto_import' => $this->request->getPost('auto_import', 'int', 0) == 1,
                'productos_por_sync' => $this->request->getPost('productos_por_sync', 'int', 100),
                'calificacion_minima' => $this->request->getPost('calificacion_minima', 'float', 0)
            ];
            
            $categoriasPermitidas = $this->request->getPost('categorias_permitidas', 'string');
            if ($categoriasPermitidas) {
                $configuracion['categorias_permitidas'] = array_map('trim', explode(',', $categoriasPermitidas));
            }
            
            $proveedor->setConfiguracion($configuracion);
            
            if ($proveedor->save()) {
                $this->flash->success('Proveedor creado exitosamente');
                return $this->response->redirect('/admin/dropshipping/proveedores');
            } else {
                $this->flash->error('Error al crear el proveedor: ' . implode(', ', $proveedor->getMessages()));
            }
        }
    }

    /**
     * Editar proveedor
     */
    public function editarProveedorAction($id)
    {
        $proveedor = ProveedorDropshipping::findFirst($id);
        
        if (!$proveedor) {
            $this->flash->error('Proveedor no encontrado');
            return $this->response->redirect('/admin/dropshipping/proveedores');
        }

        if ($this->request->isPost()) {
            $proveedor->nombre = $this->request->getPost('nombre', 'string');
            $proveedor->api_key = $this->request->getPost('api_key', 'string');
            $proveedor->api_secret = $this->request->getPost('api_secret', 'string');
            $proveedor->activo = $this->request->getPost('activo', 'int', 0) == 1;
            $proveedor->limite_requests_dia = $this->request->getPost('limite_requests_dia', 'int', 1000);
            
            // Actualizar configuración
            $configuracion = $proveedor->getConfiguracion();
            $configuracion['margen_defecto'] = $this->request->getPost('margen_defecto', 'float', 30);
            $configuracion['precio_minimo'] = $this->request->getPost('precio_minimo', 'float', 1);
            $configuracion['precio_maximo'] = $this->request->getPost('precio_maximo', 'float', 10000);
            $configuracion['auto_import'] = $this->request->getPost('auto_import', 'int', 0) == 1;
            $configuracion['productos_por_sync'] = $this->request->getPost('productos_por_sync', 'int', 100);
            $configuracion['calificacion_minima'] = $this->request->getPost('calificacion_minima', 'float', 0);
            
            $categoriasPermitidas = $this->request->getPost('categorias_permitidas', 'string');
            if ($categoriasPermitidas) {
                $configuracion['categorias_permitidas'] = array_map('trim', explode(',', $categoriasPermitidas));
            } else {
                unset($configuracion['categorias_permitidas']);
            }
            
            $proveedor->setConfiguracion($configuracion);
            
            if ($proveedor->save()) {
                $this->flash->success('Proveedor actualizado exitosamente');
                return $this->response->redirect('/admin/dropshipping/proveedores');
            } else {
                $this->flash->error('Error al actualizar el proveedor: ' . implode(', ', $proveedor->getMessages()));
            }
        }

        $this->view->setVar('proveedor', $proveedor);
    }

    /**
     * Eliminar proveedor
     */
    public function eliminarProveedorAction($id)
    {
        $proveedor = ProveedorDropshipping::findFirst($id);
        
        if (!$proveedor) {
            $this->flash->error('Proveedor no encontrado');
            return $this->response->redirect('/admin/dropshipping/proveedores');
        }

        if ($proveedor->delete()) {
            $this->flash->success('Proveedor eliminado exitosamente');
        } else {
            $this->flash->error('Error al eliminar el proveedor');
        }

        return $this->response->redirect('/admin/dropshipping/proveedores');
    }

    /**
     * Probar conexión con proveedor
     */
    public function probarConexionAction($id)
    {
        $proveedor = ProveedorDropshipping::findFirst($id);
        
        if (!$proveedor) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Proveedor no encontrado'
            ]);
        }

        try {
            $adapter = $proveedor->getAdapter();
            $valido = $adapter->validarCredenciales();
            
            return $this->response->setJsonContent([
                'success' => $valido,
                'message' => $valido ? 'Conexión exitosa' : 'Error en las credenciales'
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Gestión de productos externos
     */
    public function productosExternosAction()
    {
        $page = $this->request->getQuery('page', 'int', 1);
        $proveedorId = $this->request->getQuery('proveedor_id', 'int');
        $busqueda = $this->request->getQuery('busqueda', 'string');
        $disponible = $this->request->getQuery('disponible', 'string');
        
        $conditions = [];
        $bind = [];
        
        if ($proveedorId) {
            $conditions[] = 'proveedor_id = ?';
            $bind[] = $proveedorId;
        }
        
        if ($busqueda) {
            $conditions[] = 'titulo ILIKE ?';
            $bind[] = "%{$busqueda}%";
        }
        
        if ($disponible !== null && $disponible !== '') {
            $conditions[] = 'disponible = ?';
            $bind[] = $disponible === '1';
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '';
        
        $productos = ProductoExterno::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'created_at DESC',
            'limit' => 20,
            'offset' => ($page - 1) * 20
        ]);
        
        $totalProductos = ProductoExterno::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        $proveedores = ProveedorDropshipping::find(['order' => 'nombre ASC']);
        
        $this->view->setVars([
            'productos' => $productos,
            'proveedores' => $proveedores,
            'totalProductos' => $totalProductos,
            'page' => $page,
            'totalPages' => ceil($totalProductos / 20),
            'filtros' => [
                'proveedor_id' => $proveedorId,
                'busqueda' => $busqueda,
                'disponible' => $disponible
            ]
        ]);
    }

    /**
     * Importar producto externo al catálogo interno
     */
    public function importarProductoAction($id)
    {
        $productoExterno = ProductoExterno::findFirst($id);
        
        if (!$productoExterno) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Producto no encontrado'
            ]);
        }

        try {
            $productoExterno->sincronizarConProductoInterno();
            
            return $this->response->setJsonContent([
                'success' => true,
                'message' => 'Producto importado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJsonContent([
                'success' => false,
                'message' => 'Error al importar: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Sincronización manual
     */
    public function sincronizarAction()
    {
        if ($this->request->isPost()) {
            $proveedorId = $this->request->getPost('proveedor_id', 'int');
            
            if ($proveedorId) {
                // Sincronizar proveedor específico
                $proveedor = ProveedorDropshipping::findFirst($proveedorId);
                
                if (!$proveedor) {
                    $this->flash->error('Proveedor no encontrado');
                    return $this->response->redirect('/admin/dropshipping');
                }
                
                try {
                    $syncService = new SyncService();
                    $resultado = $syncService->sincronizarProveedor($proveedor);
                    
                    $this->flash->success('Sincronización completada exitosamente');
                    
                } catch (\Exception $e) {
                    $this->flash->error('Error en la sincronización: ' . $e->getMessage());
                }
                
            } else {
                // Sincronizar todos los proveedores
                try {
                    $syncService = new SyncService();
                    $resultados = $syncService->sincronizarTodosLosProveedores();
                    
                    $exitosos = count(array_filter($resultados, function($r) { return $r['exito'] ?? false; }));
                    $total = count($resultados);
                    
                    $this->flash->success("Sincronización completada: {$exitosos}/{$total} proveedores exitosos");
                    
                } catch (\Exception $e) {
                    $this->flash->error('Error en la sincronización: ' . $e->getMessage());
                }
            }
        }

        return $this->response->redirect('/admin/dropshipping');
    }

    /**
     * Historial de sincronizaciones
     */
    public function historialSyncAction()
    {
        $page = $this->request->getQuery('page', 'int', 1);
        $proveedorId = $this->request->getQuery('proveedor_id', 'int');
        
        $conditions = [];
        $bind = [];
        
        if ($proveedorId) {
            $conditions[] = 'proveedor_id = ?';
            $bind[] = $proveedorId;
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '';
        
        $historial = SyncHistory::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'created_at DESC',
            'limit' => 20,
            'offset' => ($page - 1) * 20
        ]);
        
        $totalRegistros = SyncHistory::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        $proveedores = ProveedorDropshipping::find(['order' => 'nombre ASC']);
        
        // Estadísticas generales
        $estadisticas = SyncHistory::getEstadisticasGenerales(30);
        
        $this->view->setVars([
            'historial' => $historial,
            'proveedores' => $proveedores,
            'estadisticas' => $estadisticas,
            'totalRegistros' => $totalRegistros,
            'page' => $page,
            'totalPages' => ceil($totalRegistros / 20),
            'proveedorId' => $proveedorId
        ]);
    }

    /**
     * Logs de API
     */
    public function logsApiAction()
    {
        $page = $this->request->getQuery('page', 'int', 1);
        $proveedorId = $this->request->getQuery('proveedor_id', 'int');
        $soloErrores = $this->request->getQuery('solo_errores', 'int', 0);
        
        $conditions = [];
        $bind = [];
        
        if ($proveedorId) {
            $conditions[] = 'proveedor_id = ?';
            $bind[] = $proveedorId;
        }
        
        if ($soloErrores) {
            $conditions[] = '(status_code >= 400 OR error_message IS NOT NULL)';
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '';
        
        $logs = ApiLog::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'created_at DESC',
            'limit' => 50,
            'offset' => ($page - 1) * 50
        ]);
        
        $totalLogs = ApiLog::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        $proveedores = ProveedorDropshipping::find(['order' => 'nombre ASC']);
        
        // Estadísticas de API
        $estadisticasApi = ApiLog::getEstadisticas($proveedorId, 7);
        
        $this->view->setVars([
            'logs' => $logs,
            'proveedores' => $proveedores,
            'estadisticasApi' => $estadisticasApi,
            'totalLogs' => $totalLogs,
            'page' => $page,
            'totalPages' => ceil($totalLogs / 50),
            'filtros' => [
                'proveedor_id' => $proveedorId,
                'solo_errores' => $soloErrores
            ]
        ]);
    }

    /**
     * Gestión de alertas
     */
    public function alertasAction()
    {
        $page = $this->request->getQuery('page', 'int', 1);
        $nivel = $this->request->getQuery('nivel', 'string');
        $tipo = $this->request->getQuery('tipo', 'string');
        $soloNoLeidas = $this->request->getQuery('solo_no_leidas', 'int', 0);
        
        $conditions = [];
        $bind = [];
        
        if ($nivel) {
            $conditions[] = 'nivel = ?';
            $bind[] = $nivel;
        }
        
        if ($tipo) {
            $conditions[] = 'tipo = ?';
            $bind[] = $tipo;
        }
        
        if ($soloNoLeidas) {
            $conditions[] = 'leida = false';
        }
        
        $whereClause = !empty($conditions) ? implode(' AND ', $conditions) : '';
        
        $alertas = AlertaSistema::find([
            'conditions' => $whereClause,
            'bind' => $bind,
            'order' => 'created_at DESC',
            'limit' => 20,
            'offset' => ($page - 1) * 20
        ]);
        
        $totalAlertas = AlertaSistema::count([
            'conditions' => $whereClause,
            'bind' => $bind
        ]);
        
        // Estadísticas de alertas
        $estadisticasAlertas = AlertaSistema::contarPorTipo(7);
        
        $this->view->setVars([
            'alertas' => $alertas,
            'estadisticasAlertas' => $estadisticasAlertas,
            'totalAlertas' => $totalAlertas,
            'page' => $page,
            'totalPages' => ceil($totalAlertas / 20),
            'filtros' => [
                'nivel' => $nivel,
                'tipo' => $tipo,
                'solo_no_leidas' => $soloNoLeidas
            ]
        ]);
    }

    /**
     * Marcar alerta como leída
     */
    public function marcarAlertaLeidaAction($id)
    {
        $alerta = AlertaSistema::findFirst($id);
        
        if ($alerta && $alerta->marcarComoLeida()) {
            return $this->response->setJsonContent(['success' => true]);
        }
        
        return $this->response->setJsonContent(['success' => false]);
    }

    /**
     * Marcar todas las alertas como leídas
     */
    public function marcarTodasLeidasAction()
    {
        $alertas = AlertaSistema::find(['leida = false']);
        
        $marcadas = 0;
        foreach ($alertas as $alerta) {
            if ($alerta->marcarComoLeida()) {
                $marcadas++;
            }
        }
        
        return $this->response->setJsonContent([
            'success' => true,
            'marcadas' => $marcadas
        ]);
    }

    /**
     * Configuración general de dropshipping
     */
    public function configuracionAction()
    {
        if ($this->request->isPost()) {
            // Aquí se guardarían configuraciones generales del sistema de dropshipping
            $this->flash->success('Configuración actualizada exitosamente');
        }
        
        // Obtener configuración actual
        $configuracion = [
            'sync_automatico' => true,
            'hora_sync' => '02:00',
            'email_reportes' => 'admin@tienda.com',
            'retries_api' => 3,
            'timeout_api' => 30
        ];
        
        $this->view->setVar('configuracion', $configuracion);
    }
}

