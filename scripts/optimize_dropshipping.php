<?php

/**
 * Script de optimización para el sistema de dropshipping
 * Optimiza la base de datos y el rendimiento del sistema
 */

// Configurar el entorno
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require_once BASE_PATH . '/vendor/autoload.php';
require_once APP_PATH . '/config/config.php';
require_once APP_PATH . '/config/services.php';

use Phalcon\Di\FactoryDefault\Cli as CliDI;

class DropshippingOptimizer
{
    private $di;
    private $db;
    private $logger;

    public function __construct()
    {
        $this->di = new CliDI();
        require APP_PATH . '/config/services.php';
        
        $this->db = $this->di->getDb();
        $this->logger = $this->di->getLogger();
    }

    /**
     * Ejecuta todas las optimizaciones
     */
    public function ejecutarOptimizaciones()
    {
        echo "=== INICIANDO OPTIMIZACIONES DEL SISTEMA ===\n\n";
        
        $this->optimizarIndices();
        $this->optimizarTablas();
        $this->limpiarDatosObsoletos();
        $this->actualizarEstadisticas();
        $this->optimizarConfiguracion();
        
        echo "\n=== OPTIMIZACIONES COMPLETADAS ===\n";
    }

    /**
     * Optimiza los índices de la base de datos
     */
    private function optimizarIndices()
    {
        echo "🔧 Optimizando índices de base de datos...\n";
        
        $indices = [
            // Índices para productos externos
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_externos_proveedor_disponible 
             ON productos_externos (proveedor_id, disponible) WHERE disponible = true",
            
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_externos_precio 
             ON productos_externos (precio_proveedor) WHERE disponible = true",
            
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_productos_externos_actualizacion 
             ON productos_externos (ultima_actualizacion)",
            
            // Índices para logs de API
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_api_logs_proveedor_fecha 
             ON api_logs (proveedor_id, created_at)",
            
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_api_logs_status 
             ON api_logs (status_code, created_at)",
            
            // Índices para historial de sincronización
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_sync_history_proveedor_estado 
             ON sync_history (proveedor_id, estado, created_at)",
            
            // Índices para pedidos de dropshipping
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_pedidos_dropshipping_estado 
             ON pedidos_dropshipping (estado_externo, created_at)",
            
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_pedidos_dropshipping_tracking 
             ON pedidos_dropshipping (tracking_number) WHERE tracking_number IS NOT NULL",
            
            // Índices para alertas
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_alertas_sistema_nivel_leida 
             ON alertas_sistema (nivel, leida, created_at)"
        ];
        
        foreach ($indices as $sql) {
            try {
                $this->db->execute($sql);
                echo "  ✅ Índice creado/verificado\n";
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "  ℹ️  Índice ya existe\n";
                } else {
                    echo "  ❌ Error creando índice: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    /**
     * Optimiza las tablas de la base de datos
     */
    private function optimizarTablas()
    {
        echo "🔧 Optimizando tablas...\n";
        
        $tablas = [
            'productos_externos',
            'api_logs',
            'sync_history',
            'pedidos_dropshipping',
            'alertas_sistema',
            'proveedores_dropshipping'
        ];
        
        foreach ($tablas as $tabla) {
            try {
                // Analizar tabla para actualizar estadísticas
                $this->db->execute("ANALYZE {$tabla}");
                echo "  ✅ Tabla {$tabla} analizada\n";
                
                // Vacuum para recuperar espacio (solo si es necesario)
                $stats = $this->db->fetchOne("
                    SELECT schemaname, tablename, n_dead_tup, n_live_tup
                    FROM pg_stat_user_tables 
                    WHERE tablename = '{$tabla}'
                ");
                
                if ($stats && $stats['n_dead_tup'] > 1000) {
                    $this->db->execute("VACUUM {$tabla}");
                    echo "  ✅ Tabla {$tabla} limpiada (vacuum)\n";
                }
                
            } catch (\Exception $e) {
                echo "  ❌ Error optimizando tabla {$tabla}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Limpia datos obsoletos
     */
    private function limpiarDatosObsoletos()
    {
        echo "🧹 Limpiando datos obsoletos...\n";
        
        try {
            // Limpiar logs de API antiguos (más de 60 días)
            $logsEliminados = $this->db->execute("
                DELETE FROM api_logs 
                WHERE created_at < CURRENT_DATE - INTERVAL '60 days'
            ");
            echo "  ✅ Logs de API antiguos eliminados\n";
            
            // Limpiar historial de sincronización antiguo (más de 120 días)
            $syncEliminados = $this->db->execute("
                DELETE FROM sync_history 
                WHERE created_at < CURRENT_DATE - INTERVAL '120 days'
            ");
            echo "  ✅ Historial de sincronización antiguo eliminado\n";
            
            // Limpiar alertas leídas antiguas (más de 30 días)
            $alertasEliminadas = $this->db->execute("
                DELETE FROM alertas_sistema 
                WHERE leida = true AND created_at < CURRENT_DATE - INTERVAL '30 days'
            ");
            echo "  ✅ Alertas antiguas eliminadas\n";
            
            // Limpiar productos externos no disponibles y antiguos (más de 7 días sin actualizar)
            $productosEliminados = $this->db->execute("
                DELETE FROM productos_externos 
                WHERE disponible = false 
                AND ultima_actualizacion < CURRENT_DATE - INTERVAL '7 days'
            ");
            echo "  ✅ Productos obsoletos eliminados\n";
            
        } catch (\Exception $e) {
            echo "  ❌ Error limpiando datos: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Actualiza estadísticas del sistema
     */
    private function actualizarEstadisticas()
    {
        echo "📊 Actualizando estadísticas...\n";
        
        try {
            // Actualizar estadísticas de proveedores
            $this->db->execute("
                UPDATE proveedores_dropshipping 
                SET total_productos = (
                    SELECT COUNT(*) 
                    FROM productos_externos 
                    WHERE proveedor_id = proveedores_dropshipping.id
                ),
                productos_disponibles = (
                    SELECT COUNT(*) 
                    FROM productos_externos 
                    WHERE proveedor_id = proveedores_dropshipping.id 
                    AND disponible = true
                )
            ");
            echo "  ✅ Estadísticas de proveedores actualizadas\n";
            
            // Resetear contadores diarios si es un nuevo día
            $this->db->execute("
                UPDATE proveedores_dropshipping 
                SET requests_realizados_hoy = 0 
                WHERE ultimo_reset_contador < CURRENT_DATE
            ");
            
            $this->db->execute("
                UPDATE proveedores_dropshipping 
                SET ultimo_reset_contador = CURRENT_DATE 
                WHERE ultimo_reset_contador < CURRENT_DATE
            ");
            echo "  ✅ Contadores diarios reseteados\n";
            
        } catch (\Exception $e) {
            echo "  ❌ Error actualizando estadísticas: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Optimiza la configuración del sistema
     */
    private function optimizarConfiguracion()
    {
        echo "⚙️ Optimizando configuración...\n";
        
        try {
            // Verificar configuración de PostgreSQL
            $configs = $this->db->fetchAll("
                SELECT name, setting, unit 
                FROM pg_settings 
                WHERE name IN (
                    'shared_buffers',
                    'effective_cache_size',
                    'maintenance_work_mem',
                    'checkpoint_completion_target',
                    'wal_buffers',
                    'default_statistics_target'
                )
            ");
            
            echo "  📋 Configuración actual de PostgreSQL:\n";
            foreach ($configs as $config) {
                $valor = $config['setting'];
                if ($config['unit']) {
                    $valor .= ' ' . $config['unit'];
                }
                echo "    {$config['name']}: {$valor}\n";
            }
            
            // Sugerencias de optimización
            echo "\n  💡 Sugerencias de optimización:\n";
            echo "    - Considerar aumentar shared_buffers si hay suficiente RAM\n";
            echo "    - Ajustar effective_cache_size según la RAM disponible\n";
            echo "    - Configurar work_mem para consultas complejas\n";
            echo "    - Habilitar logging de consultas lentas\n";
            
        } catch (\Exception $e) {
            echo "  ❌ Error verificando configuración: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Genera reporte de optimización
     */
    public function generarReporte()
    {
        echo "\n📈 Generando reporte de optimización...\n";
        
        try {
            $reporte = [
                'fecha' => date('Y-m-d H:i:s'),
                'estadisticas_tablas' => [],
                'indices' => [],
                'rendimiento' => []
            ];
            
            // Estadísticas de tablas
            $tablas = $this->db->fetchAll("
                SELECT 
                    tablename,
                    n_live_tup as filas_activas,
                    n_dead_tup as filas_muertas,
                    last_vacuum,
                    last_analyze
                FROM pg_stat_user_tables 
                WHERE schemaname = 'public'
                ORDER BY n_live_tup DESC
            ");
            
            $reporte['estadisticas_tablas'] = $tablas;
            
            // Información de índices
            $indices = $this->db->fetchAll("
                SELECT 
                    indexname,
                    tablename,
                    indexdef
                FROM pg_indexes 
                WHERE schemaname = 'public'
                ORDER BY tablename, indexname
            ");
            
            $reporte['indices'] = $indices;
            
            // Consultas más lentas (si está habilitado el log)
            try {
                $consultasLentas = $this->db->fetchAll("
                    SELECT query, calls, total_time, mean_time
                    FROM pg_stat_statements 
                    ORDER BY mean_time DESC 
                    LIMIT 10
                ");
                $reporte['consultas_lentas'] = $consultasLentas;
            } catch (\Exception $e) {
                $reporte['consultas_lentas'] = "pg_stat_statements no disponible";
            }
            
            // Guardar reporte
            $archivo = BASE_PATH . '/logs/optimization_report_' . date('Y-m-d_H-i-s') . '.json';
            
            $dirLogs = dirname($archivo);
            if (!is_dir($dirLogs)) {
                mkdir($dirLogs, 0755, true);
            }
            
            file_put_contents($archivo, json_encode($reporte, JSON_PRETTY_PRINT));
            echo "  ✅ Reporte guardado en: {$archivo}\n";
            
        } catch (\Exception $e) {
            echo "  ❌ Error generando reporte: " . $e->getMessage() . "\n";
        }
    }
}

// Ejecutar optimizaciones
try {
    $optimizer = new DropshippingOptimizer();
    
    $accion = $argv[1] ?? 'all';
    
    switch ($accion) {
        case 'indices':
            $optimizer->optimizarIndices();
            break;
        case 'tablas':
            $optimizer->optimizarTablas();
            break;
        case 'limpieza':
            $optimizer->limpiarDatosObsoletos();
            break;
        case 'estadisticas':
            $optimizer->actualizarEstadisticas();
            break;
        case 'reporte':
            $optimizer->generarReporte();
            break;
        case 'all':
        default:
            $optimizer->ejecutarOptimizaciones();
            $optimizer->generarReporte();
            break;
    }
    
} catch (\Exception $e) {
    echo "Error fatal en optimización: " . $e->getMessage() . "\n";
    exit(1);
}

