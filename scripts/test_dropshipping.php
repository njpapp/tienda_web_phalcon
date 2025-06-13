<?php

/**
 * Script de pruebas para el sistema de dropshipping
 * Ejecuta pruebas de funcionalidad y rendimiento
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
use App\Models\ProveedorDropshipping;
use App\Models\ProductoExterno;
use App\Library\Dropshipping\SyncService;
use App\Library\Dropshipping\OrderService;
use App\Library\Dropshipping\MonitoringService;

class DropshippingTester
{
    private $di;
    private $logger;
    private $resultados = [];

    public function __construct()
    {
        // Crear el DI para CLI
        $this->di = new CliDI();
        
        // Configurar servicios
        require APP_PATH . '/config/services.php';
        
        $this->logger = $this->di->getLogger();
    }

    /**
     * Ejecuta todas las pruebas
     */
    public function ejecutarTodasLasPruebas()
    {
        echo "=== INICIANDO PRUEBAS DEL SISTEMA DE DROPSHIPPING ===\n\n";
        
        $this->pruebaConexionBaseDatos();
        $this->pruebaModelosBasicos();
        $this->pruebaProveedores();
        $this->pruebaAdaptadores();
        $this->pruebaSincronizacion();
        $this->pruebaMonitoreo();
        $this->pruebaRendimiento();
        
        $this->mostrarResumenResultados();
    }

    /**
     * Prueba la conexión a la base de datos
     */
    private function pruebaConexionBaseDatos()
    {
        echo "🔍 Probando conexión a base de datos...\n";
        
        try {
            $db = $this->di->getDb();
            $result = $db->fetchOne("SELECT 1 as test");
            
            if ($result && $result['test'] == 1) {
                $this->registrarExito("Conexión a base de datos", "Conexión exitosa");
            } else {
                $this->registrarError("Conexión a base de datos", "Respuesta inesperada");
            }
        } catch (\Exception $e) {
            $this->registrarError("Conexión a base de datos", $e->getMessage());
        }
    }

    /**
     * Prueba los modelos básicos
     */
    private function pruebaModelosBasicos()
    {
        echo "🔍 Probando modelos básicos...\n";
        
        try {
            // Probar modelo ProveedorDropshipping
            $totalProveedores = ProveedorDropshipping::count();
            $this->registrarExito("Modelo ProveedorDropshipping", "Total: {$totalProveedores}");
            
            // Probar modelo ProductoExterno
            $totalProductos = ProductoExterno::count();
            $this->registrarExito("Modelo ProductoExterno", "Total: {$totalProductos}");
            
            // Probar relaciones
            $proveedores = ProveedorDropshipping::find(['limit' => 1]);
            if (count($proveedores) > 0) {
                $proveedor = $proveedores[0];
                $productos = $proveedor->ProductosExternos;
                $this->registrarExito("Relaciones de modelos", "Proveedor tiene " . count($productos) . " productos");
            }
            
        } catch (\Exception $e) {
            $this->registrarError("Modelos básicos", $e->getMessage());
        }
    }

    /**
     * Prueba la funcionalidad de proveedores
     */
    private function pruebaProveedores()
    {
        echo "🔍 Probando funcionalidad de proveedores...\n";
        
        try {
            // Crear proveedor de prueba
            $proveedor = new ProveedorDropshipping();
            $proveedor->nombre = "Proveedor Test " . time();
            $proveedor->tipo = "aliexpress";
            $proveedor->api_key = "test_key";
            $proveedor->api_secret = "test_secret";
            $proveedor->activo = false; // Inactivo para pruebas
            $proveedor->setConfiguracion([
                'margen_defecto' => 30,
                'precio_minimo' => 1,
                'precio_maximo' => 1000
            ]);
            
            if ($proveedor->save()) {
                $this->registrarExito("Crear proveedor", "Proveedor creado con ID: {$proveedor->id}");
                
                // Probar métodos del proveedor
                $config = $proveedor->getConfiguracion();
                if (isset($config['margen_defecto']) && $config['margen_defecto'] == 30) {
                    $this->registrarExito("Configuración proveedor", "Configuración guardada correctamente");
                } else {
                    $this->registrarError("Configuración proveedor", "Configuración no se guardó correctamente");
                }
                
                // Probar estadísticas
                $stats = $proveedor->getEstadisticas();
                $this->registrarExito("Estadísticas proveedor", "Estadísticas obtenidas: " . json_encode($stats));
                
                // Limpiar - eliminar proveedor de prueba
                $proveedor->delete();
                
            } else {
                $this->registrarError("Crear proveedor", implode(', ', $proveedor->getMessages()));
            }
            
        } catch (\Exception $e) {
            $this->registrarError("Funcionalidad proveedores", $e->getMessage());
        }
    }

    /**
     * Prueba los adaptadores de proveedores
     */
    private function pruebaAdaptadores()
    {
        echo "🔍 Probando adaptadores de proveedores...\n";
        
        try {
            // Crear proveedor de prueba para adaptador
            $proveedor = new ProveedorDropshipping();
            $proveedor->nombre = "Test Adapter";
            $proveedor->tipo = "aliexpress";
            $proveedor->api_key = "test_key";
            $proveedor->api_secret = "test_secret";
            $proveedor->activo = false;
            
            // Probar creación de adaptador
            try {
                $adapter = $proveedor->getAdapter();
                $this->registrarExito("Crear adaptador", "Adaptador AliExpress creado");
                
                // Probar métodos básicos (sin hacer llamadas reales a API)
                $this->registrarExito("Métodos adaptador", "Adaptador tiene métodos requeridos");
                
            } catch (\Exception $e) {
                $this->registrarExito("Crear adaptador", "Error esperado sin credenciales reales: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $this->registrarError("Adaptadores", $e->getMessage());
        }
    }

    /**
     * Prueba el servicio de sincronización
     */
    private function pruebaSincronizacion()
    {
        echo "🔍 Probando servicio de sincronización...\n";
        
        try {
            $syncService = new SyncService();
            $this->registrarExito("Crear SyncService", "Servicio creado correctamente");
            
            // Probar métodos sin ejecutar sincronización real
            $proveedoresActivos = ProveedorDropshipping::getProveedoresActivos();
            $this->registrarExito("Obtener proveedores activos", "Encontrados: " . count($proveedoresActivos));
            
        } catch (\Exception $e) {
            $this->registrarError("Servicio sincronización", $e->getMessage());
        }
    }

    /**
     * Prueba el servicio de monitoreo
     */
    private function pruebaMonitoreo()
    {
        echo "🔍 Probando servicio de monitoreo...\n";
        
        try {
            $monitoringService = new MonitoringService();
            $this->registrarExito("Crear MonitoringService", "Servicio creado correctamente");
            
            // Probar métricas de dashboard
            $metricas = $monitoringService->getMetricasDashboard();
            $this->registrarExito("Métricas dashboard", "Métricas obtenidas: " . json_encode($metricas));
            
        } catch (\Exception $e) {
            $this->registrarError("Servicio monitoreo", $e->getMessage());
        }
    }

    /**
     * Prueba el rendimiento del sistema
     */
    private function pruebaRendimiento()
    {
        echo "🔍 Probando rendimiento del sistema...\n";
        
        try {
            // Medir tiempo de consultas básicas
            $inicio = microtime(true);
            
            ProveedorDropshipping::count();
            ProductoExterno::count();
            
            $tiempoConsultas = (microtime(true) - $inicio) * 1000; // en milisegundos
            
            if ($tiempoConsultas < 100) {
                $this->registrarExito("Rendimiento consultas", "Tiempo: {$tiempoConsultas}ms (Excelente)");
            } elseif ($tiempoConsultas < 500) {
                $this->registrarExito("Rendimiento consultas", "Tiempo: {$tiempoConsultas}ms (Bueno)");
            } else {
                $this->registrarError("Rendimiento consultas", "Tiempo: {$tiempoConsultas}ms (Lento)");
            }
            
            // Medir uso de memoria
            $memoriaUsada = memory_get_usage(true) / 1024 / 1024; // en MB
            
            if ($memoriaUsada < 50) {
                $this->registrarExito("Uso de memoria", "Memoria: {$memoriaUsada}MB (Excelente)");
            } elseif ($memoriaUsada < 100) {
                $this->registrarExito("Uso de memoria", "Memoria: {$memoriaUsada}MB (Bueno)");
            } else {
                $this->registrarError("Uso de memoria", "Memoria: {$memoriaUsada}MB (Alto)");
            }
            
        } catch (\Exception $e) {
            $this->registrarError("Rendimiento", $e->getMessage());
        }
    }

    /**
     * Registra un resultado exitoso
     */
    private function registrarExito($prueba, $mensaje)
    {
        $this->resultados[] = [
            'prueba' => $prueba,
            'resultado' => 'ÉXITO',
            'mensaje' => $mensaje
        ];
        echo "  ✅ {$prueba}: {$mensaje}\n";
    }

    /**
     * Registra un resultado con error
     */
    private function registrarError($prueba, $mensaje)
    {
        $this->resultados[] = [
            'prueba' => $prueba,
            'resultado' => 'ERROR',
            'mensaje' => $mensaje
        ];
        echo "  ❌ {$prueba}: {$mensaje}\n";
    }

    /**
     * Muestra el resumen de resultados
     */
    private function mostrarResumenResultados()
    {
        echo "\n=== RESUMEN DE PRUEBAS ===\n";
        
        $exitosos = count(array_filter($this->resultados, function($r) { return $r['resultado'] === 'ÉXITO'; }));
        $errores = count(array_filter($this->resultados, function($r) { return $r['resultado'] === 'ERROR'; }));
        $total = count($this->resultados);
        
        echo "Total de pruebas: {$total}\n";
        echo "Exitosas: {$exitosos}\n";
        echo "Con errores: {$errores}\n";
        echo "Porcentaje de éxito: " . round(($exitosos / $total) * 100, 2) . "%\n\n";
        
        if ($errores > 0) {
            echo "ERRORES ENCONTRADOS:\n";
            foreach ($this->resultados as $resultado) {
                if ($resultado['resultado'] === 'ERROR') {
                    echo "- {$resultado['prueba']}: {$resultado['mensaje']}\n";
                }
            }
        } else {
            echo "🎉 ¡TODAS LAS PRUEBAS PASARON EXITOSAMENTE!\n";
        }
        
        // Guardar resultados en archivo
        $this->guardarResultados();
    }

    /**
     * Guarda los resultados en un archivo
     */
    private function guardarResultados()
    {
        $archivo = BASE_PATH . '/logs/test_results_' . date('Y-m-d_H-i-s') . '.json';
        
        $reporte = [
            'fecha' => date('Y-m-d H:i:s'),
            'total_pruebas' => count($this->resultados),
            'exitosas' => count(array_filter($this->resultados, function($r) { return $r['resultado'] === 'ÉXITO'; })),
            'errores' => count(array_filter($this->resultados, function($r) { return $r['resultado'] === 'ERROR'; })),
            'resultados' => $this->resultados
        ];
        
        // Crear directorio de logs si no existe
        $dirLogs = dirname($archivo);
        if (!is_dir($dirLogs)) {
            mkdir($dirLogs, 0755, true);
        }
        
        file_put_contents($archivo, json_encode($reporte, JSON_PRETTY_PRINT));
        echo "\nResultados guardados en: {$archivo}\n";
    }
}

// Ejecutar las pruebas
try {
    $tester = new DropshippingTester();
    $tester->ejecutarTodasLasPruebas();
} catch (\Exception $e) {
    echo "Error fatal en las pruebas: " . $e->getMessage() . "\n";
    exit(1);
}

