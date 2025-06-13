<?php

namespace App\Library\Dropshipping;

/**
 * Interfaz común para todos los adaptadores de proveedores de dropshipping
 */
interface ProveedorAdapterInterface
{
    /**
     * Obtiene productos del proveedor
     */
    public function obtenerProductos($categoria = null, $limite = 100, $offset = 0);
    
    /**
     * Obtiene información detallada de un producto específico
     */
    public function obtenerProducto($productoId);
    
    /**
     * Actualiza precios de productos
     */
    public function actualizarPrecios($productosIds);
    
    /**
     * Verifica disponibilidad de productos
     */
    public function verificarDisponibilidad($productosIds);
    
    /**
     * Crea un pedido en el proveedor
     */
    public function crearPedido($producto, $detalle, $pedido);
    
    /**
     * Obtiene el estado de un pedido
     */
    public function obtenerEstadoPedido($pedidoId);
    
    /**
     * Obtiene información de seguimiento
     */
    public function obtenerSeguimiento($trackingNumber);
    
    /**
     * Busca productos por término
     */
    public function buscarProductos($termino, $categoria = null, $limite = 50);
    
    /**
     * Obtiene categorías disponibles
     */
    public function obtenerCategorias();
    
    /**
     * Valida las credenciales de la API
     */
    public function validarCredenciales();
}

/**
 * Clase base para adaptadores de proveedores
 */
abstract class BaseAdapter implements ProveedorAdapterInterface
{
    protected $proveedor;
    protected $config;
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;
    protected $rateLimitDelay = 1; // segundos entre requests

    public function __construct($proveedor)
    {
        $this->proveedor = $proveedor;
        $this->config = $proveedor->getConfiguracion();
        $this->apiKey = $proveedor->api_key;
        $this->apiSecret = $proveedor->api_secret;
        $this->initializeAdapter();
    }

    /**
     * Inicialización específica del adaptador
     */
    abstract protected function initializeAdapter();

    /**
     * Realiza una petición HTTP a la API
     */
    protected function makeApiCall($endpoint, $params = [], $method = 'GET')
    {
        // Verificar límite de requests
        if (!$this->proveedor->puedeHacerRequest()) {
            throw new \Exception('Límite de requests diarios alcanzado para el proveedor');
        }

        $startTime = microtime(true);
        $url = $this->baseUrl . $endpoint;
        
        try {
            $ch = curl_init();
            
            // Configuración básica de cURL
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'TiendaWeb-Dropshipping/1.0',
                CURLOPT_HTTPHEADER => $this->getHeaders(),
            ]);
            
            // Configurar método y datos
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            } elseif ($method === 'GET' && !empty($params)) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $responseTime = (microtime(true) - $startTime) * 1000; // en milisegundos
            
            // Registrar la llamada a la API
            $this->logApiCall($endpoint, $method, $params, $response, $httpCode, $responseTime, $error);
            
            // Incrementar contador de requests
            $this->proveedor->incrementarRequests();
            
            // Rate limiting
            if ($this->rateLimitDelay > 0) {
                sleep($this->rateLimitDelay);
            }
            
            if ($error) {
                throw new \Exception("Error de cURL: {$error}");
            }
            
            if ($httpCode >= 400) {
                throw new \Exception("Error HTTP {$httpCode}: {$response}");
            }
            
            return json_decode($response, true);
            
        } catch (\Exception $e) {
            $this->logApiCall($endpoint, $method, $params, null, 0, 0, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene los headers para las peticiones HTTP
     */
    abstract protected function getHeaders();

    /**
     * Registra las llamadas a la API
     */
    protected function logApiCall($endpoint, $method, $requestData, $responseData, $statusCode, $responseTime, $error = null)
    {
        $log = new \App\Models\ApiLog();
        $log->proveedor_id = $this->proveedor->id;
        $log->endpoint = $endpoint;
        $log->metodo = $method;
        $log->request_data = $requestData;
        $log->response_data = is_string($responseData) ? json_decode($responseData, true) : $responseData;
        $log->status_code = $statusCode;
        $log->tiempo_respuesta = (int)$responseTime;
        $log->error_message = $error;
        $log->save();
    }

    /**
     * Normaliza los datos de un producto
     */
    protected function normalizarProducto($datosProveedor)
    {
        return [
            'producto_id_externo' => $datosProveedor['id'] ?? '',
            'titulo' => $datosProveedor['title'] ?? $datosProveedor['name'] ?? '',
            'descripcion' => $datosProveedor['description'] ?? '',
            'precio' => (float)($datosProveedor['price'] ?? 0),
            'disponible' => (bool)($datosProveedor['available'] ?? true),
            'stock' => (int)($datosProveedor['stock'] ?? 0),
            'imagen' => $datosProveedor['image'] ?? $datosProveedor['main_image'] ?? '',
            'imagenes_adicionales' => $datosProveedor['images'] ?? [],
            'categoria' => $datosProveedor['category'] ?? '',
            'peso' => (float)($datosProveedor['weight'] ?? 0),
            'dimensiones' => $datosProveedor['dimensions'] ?? [],
            'tiempo_envio_min' => (int)($datosProveedor['shipping_time_min'] ?? 7),
            'tiempo_envio_max' => (int)($datosProveedor['shipping_time_max'] ?? 15),
            'calificacion' => (float)($datosProveedor['rating'] ?? 0),
            'numero_reviews' => (int)($datosProveedor['reviews_count'] ?? 0),
            'url_producto' => $datosProveedor['url'] ?? '',
            'datos_adicionales' => $datosProveedor
        ];
    }

    /**
     * Maneja errores de la API
     */
    protected function manejarError($response, $context = '')
    {
        $mensaje = "Error en {$context}";
        
        if (isset($response['error'])) {
            $mensaje .= ": " . $response['error'];
        } elseif (isset($response['message'])) {
            $mensaje .= ": " . $response['message'];
        }
        
        throw new \Exception($mensaje);
    }

    /**
     * Valida los parámetros requeridos
     */
    protected function validarParametros($params, $requeridos)
    {
        foreach ($requeridos as $param) {
            if (!isset($params[$param]) || empty($params[$param])) {
                throw new \Exception("Parámetro requerido faltante: {$param}");
            }
        }
    }

    /**
     * Aplica configuración de filtros
     */
    protected function aplicarFiltros($productos)
    {
        $config = $this->config;
        $productosFiltrados = [];
        
        foreach ($productos as $producto) {
            // Filtro por precio máximo
            if (isset($config['precio_maximo']) && $producto['precio'] > $config['precio_maximo']) {
                continue;
            }
            
            // Filtro por precio mínimo
            if (isset($config['precio_minimo']) && $producto['precio'] < $config['precio_minimo']) {
                continue;
            }
            
            // Filtro por categorías permitidas
            if (isset($config['categorias_permitidas']) && !empty($config['categorias_permitidas'])) {
                if (!in_array($producto['categoria'], $config['categorias_permitidas'])) {
                    continue;
                }
            }
            
            // Filtro por calificación mínima
            if (isset($config['calificacion_minima']) && $producto['calificacion'] < $config['calificacion_minima']) {
                continue;
            }
            
            $productosFiltrados[] = $producto;
        }
        
        return $productosFiltrados;
    }

    /**
     * Calcula el precio de venta con margen
     */
    protected function calcularPrecioVenta($precioProveedor)
    {
        $margen = $this->config['margen_defecto'] ?? 30; // 30% por defecto
        return $precioProveedor * (1 + ($margen / 100));
    }
}

