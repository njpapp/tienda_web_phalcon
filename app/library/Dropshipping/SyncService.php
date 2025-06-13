<?php

namespace App\Library\Dropshipping;

use App\Models\ProveedorDropshipping;
use App\Models\ProductoExterno;
use App\Models\SyncHistory;
use App\Models\AlertaSistema;

/**
 * Servicio de sincronización para dropshipping
 * Maneja la sincronización diaria de productos, precios y disponibilidad
 */
class SyncService
{
    private $logger;
    private $config;

    public function __construct()
    {
        $this->logger = \Phalcon\Di::getDefault()->getLogger();
        $this->config = \Phalcon\Di::getDefault()->getConfig();
    }

    /**
     * Sincroniza todos los proveedores activos
     */
    public function sincronizarTodosLosProveedores()
    {
        $proveedores = ProveedorDropshipping::getProveedoresActivos();
        $resultados = [];

        $this->logger->info("Iniciando sincronización de {count($proveedores)} proveedores");

        foreach ($proveedores as $proveedor) {
            try {
                $resultado = $this->sincronizarProveedor($proveedor);
                $resultados[$proveedor->nombre] = $resultado;
                
                // Pausa entre proveedores para evitar sobrecarga
                sleep(5);
                
            } catch (\Exception $e) {
                $this->logger->error("Error sincronizando proveedor {$proveedor->nombre}: " . $e->getMessage());
                $resultados[$proveedor->nombre] = [
                    'exito' => false,
                    'error' => $e->getMessage()
                ];
                
                // Crear alerta de error
                $this->crearAlerta('sync_error', $proveedor->id, 
                    "Error en sincronización", 
                    "Error sincronizando proveedor {$proveedor->nombre}: " . $e->getMessage(),
                    'error'
                );
            }
        }

        $this->enviarReporteSincronizacion($resultados);
        return $resultados;
    }

    /**
     * Sincroniza un proveedor específico
     */
    public function sincronizarProveedor(ProveedorDropshipping $proveedor)
    {
        $this->logger->info("Iniciando sincronización del proveedor: {$proveedor->nombre}");

        // Crear registro de sincronización
        $syncHistory = new SyncHistory();
        $syncHistory->proveedor_id = $proveedor->id;
        $syncHistory->tipo_sync = 'completa';
        $syncHistory->estado = 'iniciado';
        $syncHistory->save();

        $startTime = time();
        $estadisticas = [
            'productos_procesados' => 0,
            'productos_nuevos' => 0,
            'productos_actualizados' => 0,
            'productos_eliminados' => 0,
            'errores' => 0
        ];

        try {
            $adapter = $proveedor->getAdapter();

            // 1. Sincronizar productos nuevos
            $this->logger->info("Sincronizando productos nuevos para {$proveedor->nombre}");
            $resultadoProductos = $this->sincronizarProductosNuevos($adapter, $proveedor);
            $estadisticas['productos_nuevos'] = $resultadoProductos['nuevos'];
            $estadisticas['productos_procesados'] += $resultadoProductos['procesados'];

            // 2. Actualizar precios y disponibilidad
            $this->logger->info("Actualizando precios para {$proveedor->nombre}");
            $resultadoPrecios = $this->actualizarPreciosYDisponibilidad($adapter, $proveedor);
            $estadisticas['productos_actualizados'] = $resultadoPrecios['actualizados'];
            $estadisticas['productos_procesados'] += $resultadoPrecios['procesados'];

            // 3. Verificar productos obsoletos
            $this->logger->info("Verificando productos obsoletos para {$proveedor->nombre}");
            $resultadoObsoletos = $this->verificarProductosObsoletos($adapter, $proveedor);
            $estadisticas['productos_eliminados'] = $resultadoObsoletos['eliminados'];

            // Actualizar registro de sincronización
            $syncHistory->estado = 'completado';
            $syncHistory->productos_procesados = $estadisticas['productos_procesados'];
            $syncHistory->productos_nuevos = $estadisticas['productos_nuevos'];
            $syncHistory->productos_actualizados = $estadisticas['productos_actualizados'];
            $syncHistory->productos_eliminados = $estadisticas['productos_eliminados'];
            $syncHistory->errores_encontrados = $estadisticas['errores'];
            $syncHistory->tiempo_ejecucion = time() - $startTime;
            $syncHistory->estadisticas = $estadisticas;
            $syncHistory->completed_at = date('Y-m-d H:i:s');
            $syncHistory->save();

            // Actualizar última sincronización del proveedor
            $proveedor->actualizarUltimaSincronizacion();

            $this->logger->info("Sincronización completada para {$proveedor->nombre}. Estadísticas: " . json_encode($estadisticas));

            return [
                'exito' => true,
                'estadisticas' => $estadisticas,
                'tiempo_ejecucion' => time() - $startTime
            ];

        } catch (\Exception $e) {
            // Actualizar registro con error
            $syncHistory->estado = 'error';
            $syncHistory->detalles_error = $e->getMessage();
            $syncHistory->tiempo_ejecucion = time() - $startTime;
            $syncHistory->completed_at = date('Y-m-d H:i:s');
            $syncHistory->save();

            throw $e;
        }
    }

    /**
     * Sincroniza productos nuevos desde el proveedor
     */
    private function sincronizarProductosNuevos($adapter, $proveedor)
    {
        $config = $proveedor->getConfiguracion();
        $limite = $config['productos_por_sync'] ?? 100;
        $categoriasPermitidas = $config['categorias_permitidas'] ?? [];
        
        $nuevos = 0;
        $procesados = 0;

        try {
            // Si hay categorías específicas, sincronizar por categoría
            if (!empty($categoriasPermitidas)) {
                foreach ($categoriasPermitidas as $categoria) {
                    $productos = $adapter->obtenerProductos($categoria, $limite);
                    $resultado = $this->procesarProductosNuevos($productos, $proveedor);
                    $nuevos += $resultado['nuevos'];
                    $procesados += $resultado['procesados'];
                }
            } else {
                // Sincronizar productos generales
                $productos = $adapter->obtenerProductos(null, $limite);
                $resultado = $this->procesarProductosNuevos($productos, $proveedor);
                $nuevos += $resultado['nuevos'];
                $procesados += $resultado['procesados'];
            }

        } catch (\Exception $e) {
            $this->logger->error("Error sincronizando productos nuevos: " . $e->getMessage());
            throw $e;
        }

        return [
            'nuevos' => $nuevos,
            'procesados' => $procesados
        ];
    }

    /**
     * Procesa una lista de productos del proveedor
     */
    private function procesarProductosNuevos($productos, $proveedor)
    {
        $nuevos = 0;
        $procesados = 0;

        foreach ($productos as $datosProducto) {
            $procesados++;

            try {
                // Verificar si el producto ya existe
                $productoExistente = ProductoExterno::findFirst([
                    'conditions' => 'proveedor_id = ? AND producto_id_externo = ?',
                    'bind' => [$proveedor->id, $datosProducto['producto_id_externo']]
                ]);

                if (!$productoExistente) {
                    // Crear nuevo producto externo
                    $productoExterno = new ProductoExterno();
                    $productoExterno->proveedor_id = $proveedor->id;
                    $productoExterno->producto_id_externo = $datosProducto['producto_id_externo'];
                    $productoExterno->titulo = $datosProducto['titulo'];
                    $productoExterno->descripcion = $datosProducto['descripcion'];
                    $productoExterno->precio_proveedor = $datosProducto['precio'];
                    $productoExterno->precio_venta = $this->calcularPrecioVenta($datosProducto['precio'], $proveedor);
                    $productoExterno->disponible = $datosProducto['disponible'];
                    $productoExterno->stock_externo = $datosProducto['stock'];
                    $productoExterno->url_producto = $datosProducto['url_producto'];
                    $productoExterno->imagen_principal = $datosProducto['imagen'];
                    $productoExterno->imagenes_adicionales = $datosProducto['imagenes_adicionales'];
                    $productoExterno->categoria_externa = $datosProducto['categoria'];
                    $productoExterno->peso = $datosProducto['peso'];
                    $productoExterno->dimensiones = $datosProducto['dimensiones'];
                    $productoExterno->tiempo_envio_min = $datosProducto['tiempo_envio_min'];
                    $productoExterno->tiempo_envio_max = $datosProducto['tiempo_envio_max'];
                    $productoExterno->calificacion = $datosProducto['calificacion'];
                    $productoExterno->numero_reviews = $datosProducto['numero_reviews'];
                    $productoExterno->datos_adicionales = $datosProducto['datos_adicionales'];

                    if ($productoExterno->save()) {
                        $nuevos++;
                        
                        // Auto-importar si está configurado
                        if ($proveedor->getConfiguracion()['auto_import'] ?? false) {
                            $productoExterno->sincronizarConProductoInterno();
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error("Error procesando producto {$datosProducto['producto_id_externo']}: " . $e->getMessage());
            }
        }

        return [
            'nuevos' => $nuevos,
            'procesados' => $procesados
        ];
    }

    /**
     * Actualiza precios y disponibilidad de productos existentes
     */
    private function actualizarPreciosYDisponibilidad($adapter, $proveedor)
    {
        $actualizados = 0;
        $procesados = 0;

        // Obtener productos que necesitan actualización (más de 24 horas)
        $productos = ProductoExterno::getNecesitanActualizacion(24);
        $productos = array_filter($productos->toArray(), function($p) use ($proveedor) {
            return $p['proveedor_id'] == $proveedor->id;
        });

        $this->logger->info("Actualizando " . count($productos) . " productos para {$proveedor->nombre}");

        foreach ($productos as $producto) {
            $procesados++;

            try {
                // Obtener datos actualizados del proveedor
                $datosActualizados = $adapter->obtenerProducto($producto['producto_id_externo']);
                
                if ($datosActualizados) {
                    $productoExterno = ProductoExterno::findFirst($producto['id']);
                    
                    if ($productoExterno) {
                        $cambios = false;
                        
                        // Verificar cambios en precio
                        if ($productoExterno->precio_proveedor != $datosActualizados['precio']) {
                            $precioAnterior = $productoExterno->precio_proveedor;
                            $productoExterno->precio_proveedor = $datosActualizados['precio'];
                            $productoExterno->precio_venta = $this->calcularPrecioVenta($datosActualizados['precio'], $proveedor);
                            $cambios = true;
                            
                            // Crear alerta de cambio de precio significativo (>10%)
                            $cambioPercentual = abs(($datosActualizados['precio'] - $precioAnterior) / $precioAnterior * 100);
                            if ($cambioPercentual > 10) {
                                $this->crearAlerta('price_change', $proveedor->id,
                                    "Cambio significativo de precio",
                                    "El producto {$productoExterno->titulo} cambió de precio de {$precioAnterior} a {$datosActualizados['precio']} ({$cambioPercentual}%)",
                                    'warning'
                                );
                            }
                        }
                        
                        // Verificar cambios en disponibilidad
                        if ($productoExterno->disponible != $datosActualizados['disponible']) {
                            $productoExterno->disponible = $datosActualizados['disponible'];
                            $cambios = true;
                            
                            if (!$datosActualizados['disponible']) {
                                $this->crearAlerta('product_unavailable', $proveedor->id,
                                    "Producto no disponible",
                                    "El producto {$productoExterno->titulo} ya no está disponible",
                                    'warning'
                                );
                            }
                        }
                        
                        // Actualizar stock
                        $productoExterno->stock_externo = $datosActualizados['stock'];
                        $productoExterno->calificacion = $datosActualizados['calificacion'];
                        $productoExterno->numero_reviews = $datosActualizados['numero_reviews'];
                        
                        if ($productoExterno->save()) {
                            $actualizados++;
                            
                            // Sincronizar con producto interno si existe
                            if ($productoExterno->producto_id_interno) {
                                $productoExterno->sincronizarConProductoInterno();
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error("Error actualizando producto {$producto['producto_id_externo']}: " . $e->getMessage());
            }
        }

        return [
            'actualizados' => $actualizados,
            'procesados' => $procesados
        ];
    }

    /**
     * Verifica y marca productos obsoletos
     */
    private function verificarProductosObsoletos($adapter, $proveedor)
    {
        $eliminados = 0;
        
        // Obtener productos que no se han actualizado en más de 7 días
        $productosObsoletos = ProductoExterno::find([
            'conditions' => 'proveedor_id = ? AND ultima_actualizacion < ?',
            'bind' => [$proveedor->id, date('Y-m-d H:i:s', strtotime('-7 days'))]
        ]);

        foreach ($productosObsoletos as $producto) {
            try {
                // Verificar si el producto aún existe en el proveedor
                $datosProducto = $adapter->obtenerProducto($producto->producto_id_externo);
                
                if (!$datosProducto) {
                    // Marcar como no disponible en lugar de eliminar
                    $producto->disponible = false;
                    $producto->save();
                    
                    // Actualizar producto interno si existe
                    if ($producto->producto_id_interno) {
                        $productoInterno = $producto->ProductoInterno;
                        if ($productoInterno) {
                            $productoInterno->activo = false;
                            $productoInterno->save();
                        }
                    }
                    
                    $eliminados++;
                }
                
            } catch (\Exception $e) {
                $this->logger->error("Error verificando producto obsoleto {$producto->producto_id_externo}: " . $e->getMessage());
            }
        }

        return ['eliminados' => $eliminados];
    }

    /**
     * Calcula el precio de venta basado en la configuración del proveedor
     */
    private function calcularPrecioVenta($precioProveedor, $proveedor)
    {
        $config = $proveedor->getConfiguracion();
        $margen = $config['margen_defecto'] ?? 30;
        
        return $precioProveedor * (1 + ($margen / 100));
    }

    /**
     * Crea una alerta del sistema
     */
    private function crearAlerta($tipo, $proveedorId, $titulo, $mensaje, $nivel = 'info')
    {
        $alerta = new AlertaSistema();
        $alerta->tipo = $tipo;
        $alerta->proveedor_id = $proveedorId;
        $alerta->titulo = $titulo;
        $alerta->mensaje = $mensaje;
        $alerta->nivel = $nivel;
        $alerta->save();
    }

    /**
     * Envía reporte de sincronización por email
     */
    private function enviarReporteSincronizacion($resultados)
    {
        try {
            $totalProveedores = count($resultados);
            $exitosos = count(array_filter($resultados, function($r) { return $r['exito'] ?? false; }));
            $fallidos = $totalProveedores - $exitosos;
            
            $asunto = "Reporte de Sincronización Dropshipping - " . date('Y-m-d H:i:s');
            $mensaje = "Sincronización completada:\n\n";
            $mensaje .= "Total de proveedores: {$totalProveedores}\n";
            $mensaje .= "Exitosos: {$exitosos}\n";
            $mensaje .= "Fallidos: {$fallidos}\n\n";
            
            foreach ($resultados as $proveedor => $resultado) {
                $mensaje .= "Proveedor: {$proveedor}\n";
                if ($resultado['exito'] ?? false) {
                    $stats = $resultado['estadisticas'];
                    $mensaje .= "  - Productos procesados: {$stats['productos_procesados']}\n";
                    $mensaje .= "  - Productos nuevos: {$stats['productos_nuevos']}\n";
                    $mensaje .= "  - Productos actualizados: {$stats['productos_actualizados']}\n";
                    $mensaje .= "  - Tiempo: {$resultado['tiempo_ejecucion']} segundos\n";
                } else {
                    $mensaje .= "  - Error: {$resultado['error']}\n";
                }
                $mensaje .= "\n";
            }
            
            // Aquí se enviaría el email usando el servicio de email configurado
            $this->logger->info("Reporte de sincronización generado: " . $mensaje);
            
        } catch (\Exception $e) {
            $this->logger->error("Error enviando reporte de sincronización: " . $e->getMessage());
        }
    }
}

