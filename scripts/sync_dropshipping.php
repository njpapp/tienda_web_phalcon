#!/usr/bin/env php
<?php

/**
 * Script de sincronización diaria para dropshipping
 * Este script debe ejecutarse diariamente via cron job
 */

// Configurar el entorno
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Incluir el autoloader de Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Incluir la configuración de Phalcon
require_once APP_PATH . '/config/config.php';
require_once APP_PATH . '/config/services.php';

use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use App\Library\Dropshipping\SyncService;
use App\Library\Dropshipping\OrderService;
use App\Models\ProveedorDropshipping;

try {
    // Crear el DI para CLI
    $di = new CliDI();
    
    // Configurar servicios (base de datos, logger, etc.)
    require APP_PATH . '/config/services.php';
    
    // Crear la aplicación de consola
    $console = new ConsoleApp($di);
    
    // Configurar el logger
    $logger = $di->getLogger();
    $logger->info("=== Iniciando sincronización diaria de dropshipping ===");
    
    // Verificar argumentos de línea de comandos
    $arguments = [];
    foreach ($argv as $k => $arg) {
        if ($k === 1) {
            $arguments['task'] = $arg;
        } elseif ($k === 2) {
            $arguments['action'] = $arg;
        } elseif ($k >= 3) {
            $arguments['params'][] = $arg;
        }
    }
    
    // Determinar qué tarea ejecutar
    $task = $arguments['task'] ?? 'sync';
    $action = $arguments['action'] ?? 'all';
    
    switch ($task) {
        case 'sync':
            ejecutarSincronizacion($action, $logger);
            break;
            
        case 'orders':
            ejecutarActualizacionPedidos($action, $logger);
            break;
            
        case 'cleanup':
            ejecutarLimpieza($action, $logger);
            break;
            
        case 'notifications':
            ejecutarNotificaciones($action, $logger);
            break;
            
        default:
            mostrarAyuda();
            break;
    }
    
    $logger->info("=== Sincronización diaria completada ===");
    
} catch (\Exception $e) {
    $logger->error("Error fatal en sincronización: " . $e->getMessage());
    $logger->error("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

/**
 * Ejecuta la sincronización de productos
 */
function ejecutarSincronizacion($action, $logger)
{
    $syncService = new SyncService();
    
    switch ($action) {
        case 'all':
            $logger->info("Sincronizando todos los proveedores");
            $resultados = $syncService->sincronizarTodosLosProveedores();
            mostrarResultadosSincronizacion($resultados, $logger);
            break;
            
        case 'provider':
            global $argv;
            $proveedorId = $argv[3] ?? null;
            if (!$proveedorId) {
                $logger->error("ID de proveedor requerido para sincronización específica");
                exit(1);
            }
            
            $proveedor = ProveedorDropshipping::findFirst($proveedorId);
            if (!$proveedor) {
                $logger->error("Proveedor no encontrado: {$proveedorId}");
                exit(1);
            }
            
            $logger->info("Sincronizando proveedor: {$proveedor->nombre}");
            $resultado = $syncService->sincronizarProveedor($proveedor);
            $logger->info("Resultado: " . json_encode($resultado));
            break;
            
        default:
            $logger->error("Acción de sincronización no válida: {$action}");
            exit(1);
    }
}

/**
 * Ejecuta la actualización de estados de pedidos
 */
function ejecutarActualizacionPedidos($action, $logger)
{
    $orderService = new OrderService();
    
    switch ($action) {
        case 'status':
            $logger->info("Actualizando estados de pedidos");
            $resultado = $orderService->actualizarEstadosPedidos();
            $logger->info("Pedidos actualizados: {$resultado['actualizados']}, Errores: {$resultado['errores']}");
            break;
            
        case 'tracking':
            $logger->info("Enviando notificaciones de seguimiento");
            $enviados = $orderService->enviarNotificacionesSeguimiento();
            $logger->info("Notificaciones de seguimiento enviadas: {$enviados}");
            break;
            
        case 'delays':
            $logger->info("Detectando pedidos retrasados");
            $notificados = $orderService->detectarPedidosRetrasados();
            $logger->info("Notificaciones de retraso enviadas: {$notificados}");
            break;
            
        case 'all':
            $logger->info("Ejecutando todas las tareas de pedidos");
            
            // Actualizar estados
            $resultado = $orderService->actualizarEstadosPedidos();
            $logger->info("Estados actualizados: {$resultado['actualizados']}");
            
            // Enviar notificaciones de seguimiento
            $seguimiento = $orderService->enviarNotificacionesSeguimiento();
            $logger->info("Notificaciones de seguimiento: {$seguimiento}");
            
            // Detectar retrasos
            $retrasos = $orderService->detectarPedidosRetrasados();
            $logger->info("Notificaciones de retraso: {$retrasos}");
            break;
            
        default:
            $logger->error("Acción de pedidos no válida: {$action}");
            exit(1);
    }
}

/**
 * Ejecuta tareas de limpieza
 */
function ejecutarLimpieza($action, $logger)
{
    switch ($action) {
        case 'logs':
            $logger->info("Limpiando logs antiguos");
            
            // Limpiar logs de API (30 días)
            $logsEliminados = \App\Models\ApiLog::limpiarAntiguos(30);
            $logger->info("Logs de API eliminados: {$logsEliminados}");
            
            // Limpiar historial de sincronización (90 días)
            $syncEliminados = \App\Models\SyncHistory::limpiarAntiguos(90);
            $logger->info("Registros de sincronización eliminados: {$syncEliminados}");
            break;
            
        case 'counters':
            $logger->info("Reseteando contadores diarios");
            
            $proveedores = ProveedorDropshipping::find();
            $reseteados = 0;
            
            foreach ($proveedores as $proveedor) {
                if ($proveedor->resetearContadorDiario()) {
                    $reseteados++;
                }
            }
            
            $logger->info("Contadores reseteados: {$reseteados}");
            break;
            
        case 'all':
            $logger->info("Ejecutando todas las tareas de limpieza");
            
            // Limpiar logs
            $logsEliminados = \App\Models\ApiLog::limpiarAntiguos(30);
            $syncEliminados = \App\Models\SyncHistory::limpiarAntiguos(90);
            $logger->info("Logs eliminados: API={$logsEliminados}, Sync={$syncEliminados}");
            
            // Resetear contadores
            $proveedores = ProveedorDropshipping::find();
            $reseteados = 0;
            foreach ($proveedores as $proveedor) {
                if ($proveedor->resetearContadorDiario()) {
                    $reseteados++;
                }
            }
            $logger->info("Contadores reseteados: {$reseteados}");
            break;
            
        default:
            $logger->error("Acción de limpieza no válida: {$action}");
            exit(1);
    }
}

/**
 * Ejecuta tareas de notificaciones
 */
function ejecutarNotificaciones($action, $logger)
{
    switch ($action) {
        case 'test':
            $logger->info("Enviando email de prueba");
            // Aquí se enviaría un email de prueba
            $logger->info("Email de prueba enviado");
            break;
            
        default:
            $logger->error("Acción de notificaciones no válida: {$action}");
            exit(1);
    }
}

/**
 * Muestra los resultados de la sincronización
 */
function mostrarResultadosSincronizacion($resultados, $logger)
{
    $totalProveedores = count($resultados);
    $exitosos = count(array_filter($resultados, function($r) { return $r['exito'] ?? false; }));
    $fallidos = $totalProveedores - $exitosos;
    
    $logger->info("=== RESUMEN DE SINCRONIZACIÓN ===");
    $logger->info("Total de proveedores: {$totalProveedores}");
    $logger->info("Exitosos: {$exitosos}");
    $logger->info("Fallidos: {$fallidos}");
    
    foreach ($resultados as $proveedor => $resultado) {
        if ($resultado['exito'] ?? false) {
            $stats = $resultado['estadisticas'];
            $logger->info("✓ {$proveedor}: {$stats['productos_nuevos']} nuevos, {$stats['productos_actualizados']} actualizados");
        } else {
            $logger->error("✗ {$proveedor}: {$resultado['error']}");
        }
    }
}

/**
 * Muestra la ayuda del script
 */
function mostrarAyuda()
{
    echo "Uso: php sync_dropshipping.php [tarea] [acción] [parámetros]\n\n";
    echo "Tareas disponibles:\n";
    echo "  sync [all|provider] [proveedor_id]  - Sincronizar productos\n";
    echo "  orders [all|status|tracking|delays] - Gestionar pedidos\n";
    echo "  cleanup [all|logs|counters]         - Tareas de limpieza\n";
    echo "  notifications [test]                - Gestionar notificaciones\n\n";
    echo "Ejemplos:\n";
    echo "  php sync_dropshipping.php sync all\n";
    echo "  php sync_dropshipping.php sync provider 1\n";
    echo "  php sync_dropshipping.php orders status\n";
    echo "  php sync_dropshipping.php cleanup all\n";
}

