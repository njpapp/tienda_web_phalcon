<?php

namespace App\Library\Dropshipping;

use App\Models\ProveedorDropshipping;
use App\Models\ProductoExterno;
use App\Models\SyncHistory;
use App\Models\AlertaSistema;

/**
 * Servicio de monitoreo para el sistema de dropshipping
 * Supervisa el estado del sistema y genera alertas
 */
class MonitoringService
{
    private $logger;
    private $config;

    public function __construct()
    {
        $this->logger = \Phalcon\Di::getDefault()->getLogger();
        $this->config = \Phalcon\Di::getDefault()->getConfig();
    }

    /**
     * Ejecuta todas las verificaciones de monitoreo
     */
    public function ejecutarMonitoreoCompleto()
    {
        $this->logger->info("Iniciando monitoreo completo del sistema de dropshipping");
        
        $resultados = [
            'proveedores' => $this->monitorearProveedores(),
            'sincronizacion' => $this->monitorearSincronizacion(),
            'productos' => $this->monitorearProductos(),
            'pedidos' => $this->monitorearPedidos(),
            'apis' => $this->monitorearAPIs(),
            'rendimiento' => $this->monitorearRendimiento()
        ];
        
        $this->generarReporteMonitoreo($resultados);
        
        return $resultados;
    }

    /**
     * Monitorea el estado de los proveedores
     */
    private function monitorearProveedores()
    {
        $proveedores = ProveedorDropshipping::find();
        $alertas = [];
        
        foreach ($proveedores as $proveedor) {
            // Verificar si el proveedor está activo pero no se ha sincronizado recientemente
            if ($proveedor->activo && $proveedor->ultima_sincronizacion) {
                $ultimaSync = new \DateTime($proveedor->ultima_sincronizacion);
                $ahora = new \DateTime();
                $horasSinSync = $ahora->diff($ultimaSync)->h + ($ahora->diff($ultimaSync)->days * 24);
                
                if ($horasSinSync > 48) { // Más de 48 horas sin sincronización
                    $alertas[] = $this->crearAlerta('sync_outdated', $proveedor->id,
                        'Proveedor sin sincronización reciente',
                        "El proveedor {$proveedor->nombre} no se ha sincronizado en {$horasSinSync} horas",
                        'warning'
                    );
                }
            }
            
            // Verificar límites de API
            $porcentajeUso = ($proveedor->requests_realizados_hoy / $proveedor->limite_requests_dia) * 100;
            if ($porcentajeUso > 90) {
                $alertas[] = $this->crearAlerta('api_limit', $proveedor->id,
                    'Límite de API casi alcanzado',
                    "El proveedor {$proveedor->nombre} ha usado {$porcentajeUso}% de su límite diario de API",
                    'warning'
                );
            }
        }
        
        return [
            'total_proveedores' => count($proveedores),
            'activos' => count(array_filter($proveedores->toArray(), function($p) { return $p['activo']; })),
            'alertas_generadas' => count($alertas)
        ];
    }

    /**
     * Monitorea el estado de las sincronizaciones
     */
    private function monitorearSincronizacion()
    {
        // Verificar sincronizaciones fallidas en las últimas 24 horas
        $sincronizacionesFallidas = SyncHistory::find([
            'conditions' => 'estado = ? AND created_at >= ?',
            'bind' => ['error', date('Y-m-d H:i:s', strtotime('-24 hours'))],
            'order' => 'created_at DESC'
        ]);
        
        $alertas = [];
        
        foreach ($sincronizacionesFallidas as $sync) {
            $alertas[] = $this->crearAlerta('sync_error', $sync->proveedor_id,
                'Error en sincronización',
                "Sincronización fallida para proveedor ID {$sync->proveedor_id}: {$sync->detalles_error}",
                'error'
            );
        }
        
        // Verificar si no ha habido sincronizaciones exitosas en las últimas 24 horas
        $ultimaSyncExitosa = SyncHistory::findFirst([
            'conditions' => 'estado = ? AND created_at >= ?',
            'bind' => ['completado', date('Y-m-d H:i:s', strtotime('-24 hours'))],
            'order' => 'created_at DESC'
        ]);
        
        if (!$ultimaSyncExitosa) {
            $alertas[] = $this->crearAlerta('no_sync', null,
                'Sin sincronizaciones exitosas',
                'No ha habido sincronizaciones exitosas en las últimas 24 horas',
                'critical'
            );
        }
        
        return [
            'sincronizaciones_fallidas_24h' => count($sincronizacionesFallidas),
            'ultima_sincronizacion_exitosa' => $ultimaSyncExitosa ? $ultimaSyncExitosa->created_at : null,
            'alertas_generadas' => count($alertas)
        ];
    }

    /**
     * Monitorea el estado de los productos
     */
    private function monitorearProductos()
    {
        $totalProductos = ProductoExterno::count();
        $productosDisponibles = ProductoExterno::count(['disponible = true']);
        $productosDesactualizados = ProductoExterno::count([
            'ultima_actualizacion < ?',
            'bind' => [date('Y-m-d H:i:s', strtotime('-48 hours'))]
        ]);
        
        $alertas = [];
        
        // Alerta si hay muchos productos desactualizados
        $porcentajeDesactualizado = $totalProductos > 0 ? ($productosDesactualizados / $totalProductos) * 100 : 0;
        if ($porcentajeDesactualizado > 20) {
            $alertas[] = $this->crearAlerta('products_outdated', null,
                'Muchos productos desactualizados',
                "{$porcentajeDesactualizado}% de los productos no se han actualizado en 48 horas",
                'warning'
            );
        }
        
        // Alerta si hay pocos productos disponibles
        $porcentajeDisponible = $totalProductos > 0 ? ($productosDisponibles / $totalProductos) * 100 : 0;
        if ($porcentajeDisponible < 50) {
            $alertas[] = $this->crearAlerta('low_availability', null,
                'Baja disponibilidad de productos',
                "Solo {$porcentajeDisponible}% de los productos están disponibles",
                'warning'
            );
        }
        
        return [
            'total_productos' => $totalProductos,
            'productos_disponibles' => $productosDisponibles,
            'productos_desactualizados' => $productosDesactualizados,
            'porcentaje_disponible' => $porcentajeDisponible,
            'alertas_generadas' => count($alertas)
        ];
    }

    /**
     * Monitorea el estado de los pedidos
     */
    private function monitorearPedidos()
    {
        $estadisticas = \App\Models\PedidoDropshipping::getEstadisticas(7);
        $pedidosRetrasados = \App\Models\PedidoDropshipping::getRetrasados();
        
        $alertas = [];
        
        // Alerta si hay muchos pedidos retrasados
        if (count($pedidosRetrasados) > 10) {
            $alertas[] = $this->crearAlerta('many_delays', null,
                'Muchos pedidos retrasados',
                "Hay " . count($pedidosRetrasados) . " pedidos retrasados",
                'warning'
            );
        }
        
        // Alerta si el tiempo promedio de entrega es muy alto
        if ($estadisticas['dias_entrega_promedio'] > 21) {
            $alertas[] = $this->crearAlerta('slow_delivery', null,
                'Tiempo de entrega alto',
                "El tiempo promedio de entrega es de {$estadisticas['dias_entrega_promedio']} días",
                'warning'
            );
        }
        
        return [
            'estadisticas_pedidos' => $estadisticas,
            'pedidos_retrasados' => count($pedidosRetrasados),
            'alertas_generadas' => count($alertas)
        ];
    }

    /**
     * Monitorea el estado de las APIs
     */
    private function monitorearAPIs()
    {
        $estadisticasApi = \App\Models\ApiLog::getEstadisticas(null, 1); // Último día
        $erroresRecientes = \App\Models\ApiLog::getErrores(20);
        
        $alertas = [];
        
        // Alerta si hay muchos errores de API
        $porcentajeErrores = $estadisticasApi['total_requests'] > 0 ? 
            ($estadisticasApi['errores'] / $estadisticasApi['total_requests']) * 100 : 0;
        
        if ($porcentajeErrores > 10) {
            $alertas[] = $this->crearAlerta('high_api_errors', null,
                'Alto porcentaje de errores de API',
                "{$porcentajeErrores}% de las llamadas a API han fallado en las últimas 24 horas",
                'error'
            );
        }
        
        // Alerta si el tiempo de respuesta promedio es muy alto
        if ($estadisticasApi['tiempo_promedio'] > 5000) { // 5 segundos
            $alertas[] = $this->crearAlerta('slow_api_response', null,
                'Respuesta lenta de APIs',
                "El tiempo promedio de respuesta de APIs es de {$estadisticasApi['tiempo_promedio']}ms",
                'warning'
            );
        }
        
        return [
            'estadisticas_api' => $estadisticasApi,
            'errores_recientes' => count($erroresRecientes),
            'porcentaje_errores' => $porcentajeErrores,
            'alertas_generadas' => count($alertas)
        ];
    }

    /**
     * Monitorea el rendimiento general del sistema
     */
    private function monitorearRendimiento()
    {
        $alertas = [];
        
        // Verificar uso de memoria (si está disponible)
        $usoMemoria = memory_get_usage(true);
        $limiteMemoria = ini_get('memory_limit');
        
        if ($limiteMemoria !== '-1') {
            $limiteMB = $this->convertirABytes($limiteMemoria);
            $porcentajeMemoria = ($usoMemoria / $limiteMB) * 100;
            
            if ($porcentajeMemoria > 80) {
                $alertas[] = $this->crearAlerta('high_memory_usage', null,
                    'Alto uso de memoria',
                    "El uso de memoria está al {$porcentajeMemoria}%",
                    'warning'
                );
            }
        }
        
        // Verificar espacio en disco (si está disponible)
        $espacioLibre = disk_free_space('.');
        $espacioTotal = disk_total_space('.');
        
        if ($espacioLibre && $espacioTotal) {
            $porcentajeUso = (($espacioTotal - $espacioLibre) / $espacioTotal) * 100;
            
            if ($porcentajeUso > 90) {
                $alertas[] = $this->crearAlerta('low_disk_space', null,
                    'Poco espacio en disco',
                    "El uso de disco está al {$porcentajeUso}%",
                    'critical'
                );
            }
        }
        
        return [
            'uso_memoria_mb' => round($usoMemoria / 1024 / 1024, 2),
            'espacio_libre_gb' => $espacioLibre ? round($espacioLibre / 1024 / 1024 / 1024, 2) : null,
            'alertas_generadas' => count($alertas)
        ];
    }

    /**
     * Crea una alerta del sistema
     */
    private function crearAlerta($tipo, $proveedorId, $titulo, $mensaje, $nivel = 'info')
    {
        // Verificar si ya existe una alerta similar reciente
        $alertaExistente = AlertaSistema::findFirst([
            'conditions' => 'tipo = ? AND titulo = ? AND created_at >= ?',
            'bind' => [$tipo, $titulo, date('Y-m-d H:i:s', strtotime('-1 hour'))]
        ]);
        
        if ($alertaExistente) {
            return $alertaExistente; // No crear duplicados
        }
        
        $alerta = new AlertaSistema();
        $alerta->tipo = $tipo;
        $alerta->proveedor_id = $proveedorId;
        $alerta->titulo = $titulo;
        $alerta->mensaje = $mensaje;
        $alerta->nivel = $nivel;
        $alerta->save();
        
        $this->logger->info("Alerta creada: [{$nivel}] {$titulo}");
        
        return $alerta;
    }

    /**
     * Genera un reporte de monitoreo
     */
    private function generarReporteMonitoreo($resultados)
    {
        $this->logger->info("=== REPORTE DE MONITOREO DROPSHIPPING ===");
        
        foreach ($resultados as $categoria => $datos) {
            $this->logger->info("--- " . strtoupper($categoria) . " ---");
            
            foreach ($datos as $clave => $valor) {
                if (is_array($valor)) {
                    $this->logger->info("{$clave}: " . json_encode($valor));
                } else {
                    $this->logger->info("{$clave}: {$valor}");
                }
            }
        }
        
        // Contar alertas totales generadas
        $totalAlertas = array_sum(array_column($resultados, 'alertas_generadas'));
        $this->logger->info("TOTAL DE ALERTAS GENERADAS: {$totalAlertas}");
    }

    /**
     * Convierte una cadena de memoria a bytes
     */
    private function convertirABytes($valor)
    {
        $valor = trim($valor);
        $ultimo = strtolower($valor[strlen($valor)-1]);
        $numero = (int) $valor;
        
        switch($ultimo) {
            case 'g':
                $numero *= 1024;
            case 'm':
                $numero *= 1024;
            case 'k':
                $numero *= 1024;
        }
        
        return $numero;
    }

    /**
     * Obtiene métricas del sistema para dashboard
     */
    public function getMetricasDashboard()
    {
        return [
            'proveedores' => [
                'total' => ProveedorDropshipping::count(),
                'activos' => ProveedorDropshipping::count(['activo = true'])
            ],
            'productos' => [
                'total' => ProductoExterno::count(),
                'disponibles' => ProductoExterno::count(['disponible = true'])
            ],
            'sincronizacion' => [
                'ultima_exitosa' => SyncHistory::findFirst([
                    'conditions' => 'estado = ?',
                    'bind' => ['completado'],
                    'order' => 'created_at DESC'
                ]),
                'errores_24h' => SyncHistory::count([
                    'estado = ? AND created_at >= ?',
                    'bind' => ['error', date('Y-m-d H:i:s', strtotime('-24 hours'))]
                ])
            ],
            'alertas' => [
                'no_leidas' => AlertaSistema::count(['leida = false']),
                'criticas' => AlertaSistema::count(['nivel = ? AND leida = false', 'bind' => ['critical']])
            ]
        ];
    }
}

