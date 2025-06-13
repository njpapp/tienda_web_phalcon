<?php

namespace App\Library\Dropshipping;

/**
 * Adaptador para AliExpress Dropshipping API
 */
class AliexpressAdapter extends BaseAdapter
{
    protected function initializeAdapter()
    {
        $this->baseUrl = 'https://openservice.aliexpress.com/api/';
        $this->rateLimitDelay = 2; // AliExpress requiere más tiempo entre requests
    }

    protected function getHeaders()
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: TiendaWeb-Dropshipping/1.0'
        ];
    }

    /**
     * Genera la firma requerida por AliExpress API
     */
    private function generarFirma($params)
    {
        // Ordenar parámetros alfabéticamente
        ksort($params);
        
        // Crear string de consulta
        $queryString = '';
        foreach ($params as $key => $value) {
            $queryString .= $key . $value;
        }
        
        // Agregar secret al inicio y final
        $stringToSign = $this->apiSecret . $queryString . $this->apiSecret;
        
        // Generar hash MD5 en mayúsculas
        return strtoupper(md5($stringToSign));
    }

    /**
     * Prepara los parámetros comunes para las peticiones
     */
    private function prepararParametros($metodo, $params = [])
    {
        $parametrosComunes = [
            'app_key' => $this->apiKey,
            'method' => $metodo,
            'timestamp' => date('Y-m-d H:i:s'),
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => 'md5'
        ];
        
        $todosParametros = array_merge($parametrosComunes, $params);
        $todosParametros['sign'] = $this->generarFirma($todosParametros);
        
        return $todosParametros;
    }

    public function obtenerProductos($categoria = null, $limite = 100, $offset = 0)
    {
        $params = [
            'page_size' => min($limite, 50), // AliExpress limita a 50 por página
            'page_no' => floor($offset / 50) + 1
        ];
        
        if ($categoria) {
            $params['category_id'] = $categoria;
        }
        
        $parametros = $this->prepararParametros('aliexpress.affiliate.product.query', $params);
        
        try {
            $response = $this->makeApiCall('', $parametros, 'POST');
            
            if (isset($response['aliexpress_affiliate_product_query_response']['resp_result'])) {
                $result = $response['aliexpress_affiliate_product_query_response']['resp_result'];
                $productos = [];
                
                if (isset($result['result']['products']['product'])) {
                    foreach ($result['result']['products']['product'] as $producto) {
                        $productos[] = $this->normalizarProductoAliExpress($producto);
                    }
                }
                
                return $this->aplicarFiltros($productos);
            }
            
            return [];
            
        } catch (\Exception $e) {
            throw new \Exception("Error obteniendo productos de AliExpress: " . $e->getMessage());
        }
    }

    public function obtenerProducto($productoId)
    {
        $parametros = $this->prepararParametros('aliexpress.affiliate.product.detail.get', [
            'product_ids' => $productoId
        ]);
        
        try {
            $response = $this->makeApiCall('', $parametros, 'POST');
            
            if (isset($response['aliexpress_affiliate_product_detail_get_response']['resp_result'])) {
                $result = $response['aliexpress_affiliate_product_detail_get_response']['resp_result'];
                
                if (isset($result['result']['products']['product'][0])) {
                    return $this->normalizarProductoAliExpress($result['result']['products']['product'][0]);
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            throw new \Exception("Error obteniendo producto {$productoId} de AliExpress: " . $e->getMessage());
        }
    }

    public function actualizarPrecios($productosIds)
    {
        $precios = [];
        
        // AliExpress no tiene endpoint específico para actualizar precios
        // Necesitamos obtener cada producto individualmente
        foreach ($productosIds as $productoId) {
            try {
                $producto = $this->obtenerProducto($productoId);
                if ($producto) {
                    $precios[$productoId] = $producto['precio'];
                }
            } catch (\Exception $e) {
                // Log del error pero continuar con otros productos
                error_log("Error actualizando precio para producto {$productoId}: " . $e->getMessage());
            }
        }
        
        return $precios;
    }

    public function verificarDisponibilidad($productosIds)
    {
        $disponibilidad = [];
        
        foreach ($productosIds as $productoId) {
            try {
                $producto = $this->obtenerProducto($productoId);
                if ($producto) {
                    $disponibilidad[$productoId] = [
                        'disponible' => $producto['disponible'],
                        'stock' => $producto['stock']
                    ];
                }
            } catch (\Exception $e) {
                $disponibilidad[$productoId] = [
                    'disponible' => false,
                    'stock' => 0
                ];
            }
        }
        
        return $disponibilidad;
    }

    public function crearPedido($producto, $detalle, $pedido)
    {
        // Nota: AliExpress no permite crear pedidos automáticamente a través de API
        // Este método simula la creación del pedido para fines de demostración
        // En un entorno real, esto requeriría integración con AliExpress Dropshipping Center
        
        $pedidoData = [
            'order_id' => 'AE_' . time() . '_' . rand(1000, 9999),
            'status' => 'pending',
            'product_id' => $producto->producto_id_externo,
            'quantity' => $detalle->cantidad,
            'price' => $producto->precio_proveedor,
            'shipping_address' => [
                'name' => $pedido->cliente->getNombreCompleto(),
                'address' => $pedido->direccion_envio,
                'city' => $pedido->ciudad_envio,
                'country' => $pedido->pais_envio,
                'postal_code' => $pedido->codigo_postal_envio
            ]
        ];
        
        // En un entorno real, aquí se haría la llamada a la API de AliExpress
        // Por ahora, simulamos una respuesta exitosa
        return $pedidoData;
    }

    public function obtenerEstadoPedido($pedidoId)
    {
        // Simulación del estado del pedido
        // En un entorno real, esto consultaría la API de AliExpress
        
        $estados = ['pending', 'processing', 'shipped', 'delivered'];
        $estadoActual = $estados[array_rand($estados)];
        
        return [
            'order_id' => $pedidoId,
            'status' => $estadoActual,
            'tracking_number' => $estadoActual === 'shipped' ? 'AE' . rand(100000000, 999999999) : null,
            'estimated_delivery' => date('Y-m-d', strtotime('+' . rand(7, 21) . ' days'))
        ];
    }

    public function obtenerSeguimiento($trackingNumber)
    {
        // Simulación de información de seguimiento
        return [
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'events' => [
                [
                    'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'status' => 'shipped',
                    'description' => 'Package shipped from warehouse'
                ],
                [
                    'date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'status' => 'in_transit',
                    'description' => 'Package in transit'
                ]
            ]
        ];
    }

    public function buscarProductos($termino, $categoria = null, $limite = 50)
    {
        $params = [
            'keywords' => $termino,
            'page_size' => min($limite, 50),
            'page_no' => 1
        ];
        
        if ($categoria) {
            $params['category_id'] = $categoria;
        }
        
        $parametros = $this->prepararParametros('aliexpress.affiliate.product.query', $params);
        
        try {
            $response = $this->makeApiCall('', $parametros, 'POST');
            
            if (isset($response['aliexpress_affiliate_product_query_response']['resp_result'])) {
                $result = $response['aliexpress_affiliate_product_query_response']['resp_result'];
                $productos = [];
                
                if (isset($result['result']['products']['product'])) {
                    foreach ($result['result']['products']['product'] as $producto) {
                        $productos[] = $this->normalizarProductoAliExpress($producto);
                    }
                }
                
                return $this->aplicarFiltros($productos);
            }
            
            return [];
            
        } catch (\Exception $e) {
            throw new \Exception("Error buscando productos en AliExpress: " . $e->getMessage());
        }
    }

    public function obtenerCategorias()
    {
        $parametros = $this->prepararParametros('aliexpress.affiliate.category.get');
        
        try {
            $response = $this->makeApiCall('', $parametros, 'POST');
            
            if (isset($response['aliexpress_affiliate_category_get_response']['resp_result'])) {
                $result = $response['aliexpress_affiliate_category_get_response']['resp_result'];
                
                if (isset($result['result']['categories']['category'])) {
                    return $result['result']['categories']['category'];
                }
            }
            
            return [];
            
        } catch (\Exception $e) {
            throw new \Exception("Error obteniendo categorías de AliExpress: " . $e->getMessage());
        }
    }

    public function validarCredenciales()
    {
        try {
            // Intentar obtener categorías como test de credenciales
            $categorias = $this->obtenerCategorias();
            return !empty($categorias);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Normaliza un producto de AliExpress al formato estándar
     */
    private function normalizarProductoAliExpress($producto)
    {
        return [
            'producto_id_externo' => $producto['product_id'] ?? '',
            'titulo' => $producto['product_title'] ?? '',
            'descripcion' => $producto['product_detail_url'] ?? '', // AliExpress no proporciona descripción completa en listados
            'precio' => (float)($producto['target_sale_price'] ?? $producto['original_price'] ?? 0),
            'disponible' => true, // AliExpress solo muestra productos disponibles
            'stock' => 999, // AliExpress no proporciona stock exacto
            'imagen' => $producto['product_main_image_url'] ?? '',
            'imagenes_adicionales' => isset($producto['product_small_image_urls']) ? 
                explode(',', $producto['product_small_image_urls']) : [],
            'categoria' => $producto['first_level_category_name'] ?? '',
            'peso' => 0, // No disponible en listados básicos
            'dimensiones' => [],
            'tiempo_envio_min' => 7,
            'tiempo_envio_max' => 15,
            'calificacion' => (float)($producto['evaluate_rate'] ?? 0),
            'numero_reviews' => (int)($producto['volume'] ?? 0),
            'url_producto' => $producto['product_detail_url'] ?? '',
            'datos_adicionales' => $producto
        ];
    }
}

