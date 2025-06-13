-- Datos iniciales para el sistema de inventario y ventas
-- Ejecutar después de schema.sql

-- Insertar roles
INSERT INTO roles (nombre, descripcion) VALUES
('admin', 'Administrador del sistema con acceso completo'),
('empleado', 'Empleado con acceso a gestión de inventario y ventas'),
('cliente', 'Cliente con acceso a compras y su perfil');

-- Insertar estados de pedido
INSERT INTO estados_pedido (nombre, descripcion, color) VALUES
('pendiente', 'Pedido recibido, pendiente de procesamiento', '#FFA500'),
('procesando', 'Pedido en proceso de preparación', '#0066CC'),
('enviado', 'Pedido enviado al cliente', '#9966CC'),
('entregado', 'Pedido entregado exitosamente', '#00AA00'),
('cancelado', 'Pedido cancelado', '#CC0000'),
('devuelto', 'Pedido devuelto por el cliente', '#666666');

-- Insert-- Insertar usuarios de demostración
-- Contraseña para todos: Demo123c
-- Hash generado con: password_hash('Demo123c', PASSWORD_DEFAULT)

-- DemoAdmin (Administrador)
INSERT INTO usuarios (email, password_hash, nombre, apellido, rol_id, activo, email_verificado) VALUES
('demoadmin@tienda.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo', 'Admin', 1, true, true);

-- DemoEmpleado (Empleado)
INSERT INTO usuarios (email, password_hash, nombre, apellido, rol_id, activo, email_verificado) VALUES
('demoempleado@tienda.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo', 'Empleado', 2, true, true);

-- DemoCliente (Cliente)
INSERT INTO usuarios (email, password_hash, nombre, apellido, telefono, direccion, ciudad, codigo_postal, pais, rol_id, activo, email_verificado) VALUES
('democliente@tienda.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo', 'Cliente', '300-555-0100', 'Carrera 10 #50-25', 'Bogotá', '110111', 'Colombia', 3, true, true);

-- Insertar administrador del sistema
-- Contraseña: admin123 (hash bcrypt)
INSERT INTO usuarios (email, password_hash, nombre, apellido, rol_id, activo, email_verificado) VALUES
('admin@tienda.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'Sistema', 1, true, true);

-- Insertar empleado de prueba
-- Contraseña: empleado123
INSERT INTO usuarios (email, password_hash, nombre, apellido, rol_id, activo, email_verificado) VALUES
('empleado@tienda.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan', 'Pérez', 2, true, true);

-- Insertar cliente de prueba
-- Contraseña: cliente123
INSERT INTO usuarios (email, password_hash, nombre, apellido, telefono, direccion, ciudad, codigo_postal, pais, rol_id, activo, email_verificado) VALUES
('cliente@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María', 'González', '300-555-0123', 'Carrera 15 #123-45', 'Bogotá', '110111', 'Colombia', 3, true, true);

-- Insertar categorías de productos
INSERT INTO categorias (nombre, descripcion) VALUES
('Electrónicos', 'Dispositivos electrónicos y accesorios'),
('Ropa', 'Vestimenta y accesorios de moda'),
('Hogar', 'Artículos para el hogar y decoración'),
('Deportes', 'Equipamiento y ropa deportiva'),
('Libros', 'Libros físicos y digitales'),
('Salud', 'Productos de salud y bienestar'),
('Juguetes', 'Juguetes y entretenimiento infantil'),
('Automóvil', 'Accesorios y repuestos para automóviles');

-- Insertar proveedores
INSERT INTO proveedores (nombre, contacto, email, telefono, direccion, ciudad) VALUES
('TechSupply México', 'Carlos Rodríguez', 'ventas@techsupply.mx', '555-1001', 'Av. Tecnología 456', 'Guadalajara'),
('Moda Internacional', 'Ana López', 'compras@modaint.com', '555-1002', 'Zona Rosa 789', 'Ciudad de México'),
('Hogar y Más', 'Roberto Silva', 'info@hogarymas.mx', '555-1003', 'Industrial Norte 321', 'Monterrey'),
('Deportes Pro', 'Laura Martínez', 'ventas@deportespro.mx', '555-1004', 'Av. Deportiva 654', 'Tijuana');

-- Insertar productos de ejemplo
INSERT INTO productos (sku, nombre, descripcion, categoria_id, proveedor_id, precio_compra, precio_venta, stock_actual, stock_minimo, stock_maximo) VALUES
('ELEC001', 'Smartphone Samsung Galaxy A54', 'Teléfono inteligente con pantalla de 6.4 pulgadas, 128GB almacenamiento', 1, 1, 4500.00, 6999.00, 25, 5, 100),
('ELEC002', 'Laptop HP Pavilion 15', 'Laptop con procesador Intel i5, 8GB RAM, 512GB SSD', 1, 1, 12000.00, 18999.00, 15, 3, 50),
('ELEC003', 'Auriculares Bluetooth Sony', 'Auriculares inalámbricos con cancelación de ruido', 1, 1, 800.00, 1299.00, 50, 10, 200),
('ROPA001', 'Camiseta Polo Hombre', 'Camiseta polo de algodón 100%, disponible en varios colores', 2, 2, 150.00, 299.00, 100, 20, 500),
('ROPA002', 'Jeans Mujer Skinny', 'Jeans ajustados para mujer, tela stretch', 2, 2, 200.00, 399.00, 75, 15, 300),
('HOGAR001', 'Cafetera Automática', 'Cafetera programable de 12 tazas con filtro permanente', 3, 3, 600.00, 999.00, 30, 5, 100),
('HOGAR002', 'Juego de Sábanas Queen', 'Juego de sábanas de algodón egipcio, tamaño queen', 3, 3, 300.00, 599.00, 40, 8, 150),
('DEP001', 'Balón de Fútbol Profesional', 'Balón oficial FIFA, cuero sintético', 4, 4, 250.00, 449.00, 60, 12, 200),
('DEP002', 'Tenis Running Nike', 'Tenis para correr con tecnología Air Max', 4, 4, 800.00, 1599.00, 35, 7, 120);

-- Insertar movimientos iniciales de inventario
INSERT INTO inventario_movimientos (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id) VALUES
(1, 'entrada', 25, 0, 25, 'Stock inicial', 1),
(2, 'entrada', 15, 0, 15, 'Stock inicial', 1),
(3, 'entrada', 50, 0, 50, 'Stock inicial', 1),
(4, 'entrada', 100, 0, 100, 'Stock inicial', 1),
(5, 'entrada', 75, 0, 75, 'Stock inicial', 1),
(6, 'entrada', 30, 0, 30, 'Stock inicial', 1),
(7, 'entrada', 40, 0, 40, 'Stock inicial', 1),
(8, 'entrada', 60, 0, 60, 'Stock inicial', 1),
(9, 'entrada', 35, 0, 35, 'Stock inicial', 1);

-- Insertar configuración del sistema
INSERT INTO configuracion (clave, valor, descripcion, tipo) VALUES
('nombre_tienda', 'Mi Tienda Online', 'Nombre de la tienda', 'string'),
('moneda', 'MXN', 'Moneda del sistema', 'string'),
('iva_porcentaje', '16', 'Porcentaje de IVA', 'decimal'),
('stock_minimo_alerta', '5', 'Cantidad mínima para alerta de stock', 'integer'),
('email_notificaciones', 'admin@tienda.com', 'Email para notificaciones del sistema', 'string'),
('permitir_registro_clientes', 'true', 'Permitir registro de nuevos clientes', 'boolean'),
('metodos_pago_activos', 'efectivo,tarjeta,transferencia', 'Métodos de pago disponibles', 'string'),
('tiempo_sesion_minutos', '120', 'Tiempo de expiración de sesión en minutos', 'integer');

-- Insertar tarjeta de ejemplo para el cliente
INSERT INTO tarjetas_cliente (cliente_id, tipo, marca, ultimos_4_digitos, nombre_titular, mes_expiracion, año_expiracion, es_principal) VALUES
(3, 'credito', 'visa', '1234', 'MARIA GONZALEZ', 12, 2026, true);

-- Crear un pedido de ejemplo
INSERT INTO pedidos (numero_pedido, cliente_id, estado_id, subtotal, impuestos, total, metodo_pago, direccion_envio) VALUES
('PED-2024-001', 3, 1, 1299.00, 207.84, 1506.84, 'tarjeta', 'Calle Principal 123, Ciudad de México, 01000');

-- Detalles del pedido de ejemplo
INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
(1, 3, 1, 1299.00, 1299.00);

-- Actualizar el stock después de la venta
INSERT INTO inventario_movimientos (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, referencia, usuario_id) VALUES
(3, 'salida', 1, 50, 49, 'Venta', 'PED-2024-001', 2);

