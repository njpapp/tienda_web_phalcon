# Sistema de Inventario y Ventas - Tienda Web

Un sistema completo de gestión de inventario y ventas desarrollado con Phalcon PHP, diseñado para pequeñas y medianas empresas que necesitan una solución robusta para su tienda en línea.

## 🚀 Características Principales

### 🔐 Sistema de Autenticación Multi-Rol
- **Administradores**: Acceso completo al sistema, gestión de usuarios, productos y reportes
- **Empleados**: Gestión de inventario, procesamiento de pedidos y atención al cliente
- **Clientes**: Navegación de productos, carrito de compras, historial de pedidos

### 📦 Gestión Completa de Inventario
- Control de stock en tiempo real
- Alertas automáticas de inventario bajo
- Seguimiento de movimientos de entrada y salida
- Gestión de categorías y proveedores
- Reportes detallados de inventario

### 🛒 Sistema de Ventas Avanzado
- Carrito de compras intuitivo
- Proceso de checkout optimizado
- Gestión completa de pedidos
- Estados de pedido personalizables
- Facturación automática

### 💳 Integración de Pagos
- **PayPal**: Integración completa con PayPal para pagos seguros
- Soporte para múltiples métodos de pago
- Procesamiento seguro de transacciones
- Webhooks para confirmación automática

### 📊 Sistema de Reportes
- Dashboard con métricas en tiempo real
- Reportes de ventas por período
- Análisis de productos más vendidos
- Estadísticas de clientes
- Exportación a CSV

### 🌍 Localización Colombiana
- Configurado para el mercado colombiano
- Moneda: Peso Colombiano (COP)
- IVA: 19% (configurable)
- Zona horaria: America/Bogota

## 🛠️ Tecnologías Utilizadas

- **Backend**: Phalcon PHP 5.9.3
- **Base de Datos**: PostgreSQL 14+
- **Frontend**: HTML5, CSS3, JavaScript
- **Servidor Web**: Apache 2.4+
- **Gestión de Dependencias**: Composer
- **Integración de Pagos**: PayPal API
- **Email**: SMTP (Gmail configurado)

## 📋 Requisitos del Sistema

### Software Requerido
- Windows 10+ (64-bit recomendado)
- XAMPP 8.2+ con PHP 8.2+
- PostgreSQL 14+
- Phalcon PHP 5.9.3
- Composer

### Extensiones PHP Requeridas
- `pdo_pgsql`
- `pgsql`
- `openssl`
- `curl`
- `mbstring`
- `json`
- `session`
- `filter`
- `hash`
- `fileinfo`

## 🚀 Instalación Rápida

1. **Extraer el proyecto** en `C:\xampp\htdocs\tienda_web_phalcon\`

2. **Configurar la base de datos**:
   ```sql
   CREATE DATABASE tienda_inventario;
   ```

3. **Ejecutar scripts SQL**:
   - `sql/schema.sql` - Estructura de tablas
   - `sql/datos_iniciales.sql` - Datos iniciales

4. **Configurar variables de entorno**:
   - Copiar `.env.example` a `.env`
   - Actualizar credenciales de base de datos

5. **Instalar dependencias**:
   ```bash
   composer install
   ```

6. **Acceder al sistema**:
   - URL: `http://localhost/tienda_web_phalcon/`

## 👥 Usuarios de Demostración

| Usuario | Email | Contraseña | Rol |
|---------|-------|------------|-----|
| DemoAdmin | demoadmin@tienda.com | Demo123c | Administrador |
| DemoEmpleado | demoempleado@tienda.com | Demo123c | Empleado |
| DemoCliente | democliente@tienda.com | Demo123c | Cliente |

## 📁 Estructura del Proyecto

```
tienda_web_phalcon/
├── app/
│   ├── config/          # Configuración de la aplicación
│   ├── controllers/     # Controladores MVC
│   ├── models/          # Modelos de datos
│   ├── views/           # Vistas (plantillas)
│   └── library/         # Librerías personalizadas
├── public/
│   ├── css/             # Hojas de estilo
│   ├── js/              # JavaScript
│   ├── img/             # Imágenes
│   └── index.php        # Punto de entrada
├── sql/
│   ├── schema.sql       # Estructura de base de datos
│   └── datos_iniciales.sql # Datos de prueba
├── cache/               # Cache de la aplicación
├── logs/                # Logs del sistema
├── composer.json        # Dependencias PHP
├── .env.example         # Configuración de ejemplo
└── README.md           # Este archivo
```

## 🔧 Configuración

### Base de Datos
```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=tienda_inventario
DB_USER=postgres
DB_PASS=su_contraseña
```

### PayPal (Sandbox)
```env
PAYPAL_CLIENT_ID=AdvBS1xwvjtsmoLGCmum6w7vb3tcpbFcG2iPw6aI9vQu6MIicJQkJz2tX80l7yEDXY_wOXjkoMw0OGe2
PAYPAL_SECRET=ELau1B6EGRxFCyXrX1y5zOeguZNRG8OmHVcdoNdJOKfZeBbShSfCfz3qp4SDG_JAbFVtYE2qfm89HOxd
PAYPAL_MODE=sandbox
```

### Email SMTP
```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=cerebritonjp@gmail.com
MAIL_PASSWORD=blbxdgttcgogmzmq
```

## 📖 Documentación

- **[Manual de Instalación](MANUAL_INSTALACION.md)**: Guía completa de instalación paso a paso
- **Manual de Usuario**: Guía para usuarios finales del sistema
- **Documentación de API**: Referencia de endpoints y funciones
- **Guía de Desarrollo**: Para desarrolladores que quieran extender el sistema

## 🔒 Seguridad

- Autenticación basada en sesiones seguras
- Validación y sanitización de datos de entrada
- Protección contra inyección SQL
- Encriptación de contraseñas con bcrypt
- Tokens CSRF para formularios
- Validación de permisos por rol

## 🧪 Pruebas

### Funcionalidades Principales
1. **Autenticación**: Login/logout con diferentes roles
2. **Inventario**: CRUD de productos, control de stock
3. **Ventas**: Proceso completo de compra
4. **Pagos**: Integración con PayPal sandbox
5. **Reportes**: Generación y exportación de reportes

### URLs de Prueba
- **Tienda**: `http://localhost/tienda_web_phalcon/`
- **Login**: `http://localhost/tienda_web_phalcon/login`
- **Admin**: `http://localhost/tienda_web_phalcon/admin`
- **Cliente**: `http://localhost/tienda_web_phalcon/cliente`

## 🐛 Solución de Problemas

### Problemas Comunes

1. **Error "Phalcon extension not loaded"**
   - Verificar instalación de Phalcon
   - Revisar configuración en php.ini

2. **Error de conexión a PostgreSQL**
   - Verificar que PostgreSQL esté ejecutándose
   - Revisar credenciales en .env

3. **Permisos de carpetas**
   - Dar permisos de escritura a cache/ y logs/

Ver el [Manual de Instalación](MANUAL_INSTALACION.md) para soluciones detalladas.

## 📈 Roadmap

### Versión 1.1 (Próxima)
- [ ] Integración con más pasarelas de pago
- [ ] Sistema de cupones y descuentos
- [ ] Notificaciones push
- [ ] API REST completa

### Versión 1.2
- [ ] Aplicación móvil
- [ ] Integración con redes sociales
- [ ] Sistema de reviews y calificaciones
- [ ] Análisis avanzado con gráficos

## 🤝 Contribución

Este es un proyecto desarrollado por Manus AI. Para sugerencias o reportes de bugs, por favor contacte al equipo de desarrollo.

## 📄 Licencia

Este proyecto es propietario y está desarrollado específicamente para el cliente. Todos los derechos reservados.

## 📞 Soporte

- **Desarrollado por**: Manus AI
- **Versión**: 1.0
- **Fecha**: Diciembre 2024

---

**¡Gracias por usar nuestro Sistema de Inventario y Ventas!** 🎉

