-- Extensiones para Dropshipping
-- Agregar nuevas tablas para el sistema de dropshipping

-- Tabla de proveedores de dropshipping
CREATE TABLE proveedores_dropshipping (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('aliexpress', 'amazon', 'cj_dropshipping', 'spocket', 'doba')),
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    configuracion JSONB,
    activo BOOLEAN DEFAULT true,
    limite_requests_dia INTEGER DEFAULT 1000,
    requests_realizados_hoy INTEGER DEFAULT 0,
    ultima_sincronizacion TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de productos externos (de proveedores)
CREATE TABLE productos_externos (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id) ON DELETE CASCADE,
    producto_id_externo VARCHAR(100) NOT NULL,
    producto_id_interno INTEGER REFERENCES productos(id) ON DELETE SET NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio_proveedor DECIMAL(10,2) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    margen_ganancia DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN precio_proveedor > 0 THEN 
                ((precio_venta - precio_proveedor) / precio_proveedor * 100)
            ELSE 0 
        END
    ) STORED,
    disponible BOOLEAN DEFAULT true,
    stock_externo INTEGER DEFAULT 0,
    url_producto TEXT,
    imagen_principal TEXT,
    imagenes_adicionales JSONB,
    categoria_externa VARCHAR(100),
    peso DECIMAL(8,2),
    dimensiones JSONB,
    tiempo_envio_min INTEGER, -- días mínimos de envío
    tiempo_envio_max INTEGER, -- días máximos de envío
    calificacion DECIMAL(3,2),
    numero_reviews INTEGER DEFAULT 0,
    datos_adicionales JSONB,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(proveedor_id, producto_id_externo)
);

-- Tabla de historial de sincronización
CREATE TABLE sync_history (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id) ON DELETE CASCADE,
    tipo_sync VARCHAR(50) NOT NULL CHECK (tipo_sync IN ('productos', 'precios', 'stock', 'pedidos', 'completa')),
    estado VARCHAR(20) NOT NULL CHECK (estado IN ('iniciado', 'en_progreso', 'completado', 'error', 'cancelado')),
    productos_procesados INTEGER DEFAULT 0,
    productos_actualizados INTEGER DEFAULT 0,
    productos_nuevos INTEGER DEFAULT 0,
    productos_eliminados INTEGER DEFAULT 0,
    errores_encontrados INTEGER DEFAULT 0,
    tiempo_ejecucion INTEGER, -- en segundos
    detalles_error TEXT,
    estadisticas JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

-- Tabla de pedidos dropshipping
CREATE TABLE pedidos_dropshipping (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER REFERENCES pedidos(id) ON DELETE CASCADE,
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id),
    producto_externo_id INTEGER REFERENCES productos_externos(id),
    pedido_id_externo VARCHAR(100),
    estado_externo VARCHAR(50) DEFAULT 'pendiente',
    tracking_number VARCHAR(100),
    carrier VARCHAR(100),
    fecha_pedido_externo TIMESTAMP,
    fecha_envio TIMESTAMP,
    fecha_entrega_estimada TIMESTAMP,
    fecha_entrega_real TIMESTAMP,
    costo_producto DECIMAL(10,2),
    costo_envio DECIMAL(10,2),
    costo_total DECIMAL(10,2),
    datos_seguimiento JSONB,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de mapeo de categorías
CREATE TABLE categoria_mapping (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id) ON DELETE CASCADE,
    categoria_externa VARCHAR(100) NOT NULL,
    categoria_interna_id INTEGER REFERENCES categorias(id) ON DELETE CASCADE,
    activo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(proveedor_id, categoria_externa)
);

-- Tabla de configuración de márgenes por categoría
CREATE TABLE margenes_categoria (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id) ON DELETE CASCADE,
    categoria_id INTEGER REFERENCES categorias(id) ON DELETE CASCADE,
    margen_minimo DECIMAL(5,2) DEFAULT 20.00,
    margen_maximo DECIMAL(5,2) DEFAULT 100.00,
    precio_minimo DECIMAL(10,2) DEFAULT 1.00,
    precio_maximo DECIMAL(10,2) DEFAULT 10000.00,
    activo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(proveedor_id, categoria_id)
);

-- Tabla de logs de API
CREATE TABLE api_logs (
    id SERIAL PRIMARY KEY,
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id) ON DELETE CASCADE,
    endpoint VARCHAR(255) NOT NULL,
    metodo VARCHAR(10) NOT NULL,
    request_data JSONB,
    response_data JSONB,
    status_code INTEGER,
    tiempo_respuesta INTEGER, -- en milisegundos
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de alertas del sistema
CREATE TABLE alertas_sistema (
    id SERIAL PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('sync_error', 'api_limit', 'product_unavailable', 'price_change', 'stock_low')),
    proveedor_id INTEGER REFERENCES proveedores_dropshipping(id) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    nivel VARCHAR(20) DEFAULT 'info' CHECK (nivel IN ('info', 'warning', 'error', 'critical')),
    leida BOOLEAN DEFAULT false,
    datos_adicionales JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para optimizar consultas
CREATE INDEX idx_productos_externos_proveedor ON productos_externos(proveedor_id);
CREATE INDEX idx_productos_externos_interno ON productos_externos(producto_id_interno);
CREATE INDEX idx_productos_externos_disponible ON productos_externos(disponible);
CREATE INDEX idx_productos_externos_precio ON productos_externos(precio_venta);
CREATE INDEX idx_productos_externos_actualizacion ON productos_externos(ultima_actualizacion);

CREATE INDEX idx_sync_history_proveedor ON sync_history(proveedor_id);
CREATE INDEX idx_sync_history_tipo ON sync_history(tipo_sync);
CREATE INDEX idx_sync_history_estado ON sync_history(estado);
CREATE INDEX idx_sync_history_fecha ON sync_history(created_at);

CREATE INDEX idx_pedidos_dropshipping_pedido ON pedidos_dropshipping(pedido_id);
CREATE INDEX idx_pedidos_dropshipping_proveedor ON pedidos_dropshipping(proveedor_id);
CREATE INDEX idx_pedidos_dropshipping_estado ON pedidos_dropshipping(estado_externo);
CREATE INDEX idx_pedidos_dropshipping_tracking ON pedidos_dropshipping(tracking_number);

CREATE INDEX idx_api_logs_proveedor ON api_logs(proveedor_id);
CREATE INDEX idx_api_logs_fecha ON api_logs(created_at);
CREATE INDEX idx_api_logs_status ON api_logs(status_code);

CREATE INDEX idx_alertas_tipo ON alertas_sistema(tipo);
CREATE INDEX idx_alertas_nivel ON alertas_sistema(nivel);
CREATE INDEX idx_alertas_leida ON alertas_sistema(leida);
CREATE INDEX idx_alertas_fecha ON alertas_sistema(created_at);

-- Triggers para actualizar timestamps
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_proveedores_dropshipping_updated_at 
    BEFORE UPDATE ON proveedores_dropshipping 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_pedidos_dropshipping_updated_at 
    BEFORE UPDATE ON pedidos_dropshipping 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Función para resetear contadores diarios
CREATE OR REPLACE FUNCTION reset_daily_counters()
RETURNS void AS $$
BEGIN
    UPDATE proveedores_dropshipping 
    SET requests_realizados_hoy = 0 
    WHERE requests_realizados_hoy > 0;
END;
$$ LANGUAGE plpgsql;

-- Función para limpiar logs antiguos (más de 30 días)
CREATE OR REPLACE FUNCTION cleanup_old_logs()
RETURNS void AS $$
BEGIN
    DELETE FROM api_logs 
    WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '30 days';
    
    DELETE FROM sync_history 
    WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '90 days';
END;
$$ LANGUAGE plpgsql;

