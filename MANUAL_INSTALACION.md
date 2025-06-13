# Manual de Instalación y Configuración
## Sistema de Inventario y Ventas - Tienda Web

**Versión:** 1.0  
**Fecha:** Diciembre 2024  
**Autor:** Manus AI  
**Framework:** Phalcon PHP 5.9.3  
**Base de Datos:** PostgreSQL  

---

## Tabla de Contenidos

1. [Introducción](#introducción)
2. [Requisitos del Sistema](#requisitos-del-sistema)
3. [Instalación en Windows con XAMPP](#instalación-en-windows-con-xampp)
4. [Configuración de PostgreSQL](#configuración-de-postgresql)
5. [Configuración del Proyecto](#configuración-del-proyecto)
6. [Configuración de Servicios](#configuración-de-servicios)
7. [Inicialización de la Base de Datos](#inicialización-de-la-base-de-datos)
8. [Configuración de PayPal](#configuración-de-paypal)
9. [Configuración de Email](#configuración-de-email)
10. [Pruebas del Sistema](#pruebas-del-sistema)
11. [Solución de Problemas](#solución-de-problemas)
12. [Mantenimiento](#mantenimiento)

---



## Introducción

Este manual proporciona instrucciones detalladas para la instalación y configuración del Sistema de Inventario y Ventas desarrollado con el framework Phalcon PHP. El sistema está diseñado para funcionar en entornos Windows utilizando XAMPP como servidor local de desarrollo, con PostgreSQL como base de datos principal.

El sistema incluye funcionalidades completas para la gestión de inventarios, procesamiento de ventas, administración de usuarios, integración con PayPal para pagos, y un sistema completo de reportes. Está optimizado para pequeñas y medianas empresas que requieren una solución robusta y escalable para la gestión de su tienda en línea.

### Características Principales

- **Sistema de Autenticación Multi-Rol**: Soporte para administradores, empleados y clientes con permisos diferenciados
- **Gestión Completa de Inventario**: Control de stock, alertas de inventario bajo, movimientos de entrada y salida
- **Procesamiento de Ventas**: Carrito de compras, checkout completo, gestión de pedidos
- **Integración de Pagos**: PayPal integrado para procesamiento seguro de pagos
- **Sistema de Reportes**: Reportes detallados de ventas, inventario y clientes con exportación a CSV
- **Interfaz Responsiva**: Diseño adaptable para dispositivos móviles y escritorio
- **Configuración Localizada**: Adaptado para el mercado colombiano con COP y 19% IVA

### Usuarios de Demostración

El sistema incluye usuarios preconfigurados para facilitar las pruebas:

| Usuario | Email | Contraseña | Rol |
|---------|-------|------------|-----|
| DemoAdmin | demoadmin@tienda.com | Demo123c | Administrador |
| DemoEmpleado | demoempleado@tienda.com | Demo123c | Empleado |
| DemoCliente | democliente@tienda.com | Demo123c | Cliente |

## Requisitos del Sistema

### Requisitos de Hardware

- **Procesador**: Intel Core i3 o equivalente (mínimo), Intel Core i5 o superior (recomendado)
- **Memoria RAM**: 4 GB (mínimo), 8 GB o más (recomendado)
- **Espacio en Disco**: 2 GB de espacio libre para la instalación completa
- **Conexión a Internet**: Requerida para la integración con PayPal y servicios de email

### Requisitos de Software

#### Sistema Operativo
- Windows 10 o superior (64-bit recomendado)
- Windows Server 2016 o superior para entornos de producción

#### Componentes Principales
- **XAMPP 8.2.x o superior** que incluye:
  - Apache 2.4.x
  - PHP 8.2.x con extensiones requeridas
  - phpMyAdmin (opcional, para administración de bases de datos)

#### Base de Datos
- **PostgreSQL 14.x o superior**
- **pgAdmin 4** (recomendado para administración de PostgreSQL)

#### Extensiones PHP Requeridas
Las siguientes extensiones PHP deben estar habilitadas:
- `pdo_pgsql` - Para conectividad con PostgreSQL
- `pgsql` - Soporte nativo de PostgreSQL
- `openssl` - Para funciones de encriptación
- `curl` - Para comunicación con APIs externas (PayPal)
- `mbstring` - Para manejo de cadenas multibyte
- `json` - Para procesamiento de datos JSON
- `session` - Para manejo de sesiones
- `filter` - Para validación de datos
- `hash` - Para funciones de hash
- `fileinfo` - Para detección de tipos de archivo

#### Framework Phalcon
- **Phalcon PHP 5.9.3** - Framework principal del sistema
- Debe ser instalado como extensión de PHP

### Requisitos de Red

- **Puerto 80**: Para servidor web Apache (HTTP)
- **Puerto 443**: Para conexiones seguras HTTPS (opcional pero recomendado)
- **Puerto 5432**: Para conexiones a PostgreSQL
- **Acceso a Internet**: Para servicios de PayPal y SMTP

### Navegadores Soportados

El sistema es compatible con los siguientes navegadores:
- Google Chrome 90+
- Mozilla Firefox 88+
- Microsoft Edge 90+
- Safari 14+ (macOS)
- Opera 76+

### Consideraciones de Seguridad

- Se recomienda usar HTTPS en entornos de producción
- Configurar firewall para restringir acceso a puertos de base de datos
- Mantener actualizados todos los componentes del sistema
- Usar contraseñas seguras para todos los usuarios del sistema
- Configurar copias de seguridad regulares de la base de datos



## Instalación en Windows con XAMPP

### Paso 1: Descarga e Instalación de XAMPP

1. **Descargar XAMPP**
   - Visite el sitio oficial: https://www.apachefriends.org/download.html
   - Descargue la versión más reciente de XAMPP para Windows (PHP 8.2.x)
   - El archivo descargado será similar a: `xampp-windows-x64-8.2.x-installer.exe`

2. **Ejecutar el Instalador**
   - Ejecute el instalador como administrador (clic derecho → "Ejecutar como administrador")
   - Seleccione los componentes a instalar:
     - ✅ Apache
     - ✅ MySQL (aunque usaremos PostgreSQL, puede ser útil)
     - ✅ PHP
     - ✅ phpMyAdmin
     - ✅ Perl (opcional)
   - Seleccione la carpeta de instalación (recomendado: `C:\xampp`)

3. **Configuración Inicial**
   - Complete la instalación siguiendo las instrucciones del asistente
   - Al finalizar, inicie el Panel de Control de XAMPP
   - Inicie el servicio Apache haciendo clic en "Start"

4. **Verificar la Instalación**
   - Abra su navegador web
   - Navegue a: `http://localhost`
   - Debería ver la página de bienvenida de XAMPP

### Paso 2: Instalación de PostgreSQL

1. **Descargar PostgreSQL**
   - Visite: https://www.postgresql.org/download/windows/
   - Descargue el instalador para Windows (versión 14.x o superior)
   - Ejecute el instalador como administrador

2. **Configuración de PostgreSQL**
   - Durante la instalación, configure:
     - **Puerto**: 5432 (por defecto)
     - **Contraseña del superusuario (postgres)**: Anote esta contraseña, la necesitará
     - **Locale**: Spanish, Colombia o English, United States
   - Instale pgAdmin 4 cuando se le solicite

3. **Verificar la Instalación**
   - Abra pgAdmin 4
   - Conéctese al servidor local usando la contraseña configurada
   - Cree una nueva base de datos llamada `tienda_inventario`

### Paso 3: Instalación de Phalcon PHP

1. **Descargar Phalcon**
   - Visite: https://phalcon.io/en-us/download/windows
   - Descargue la versión correspondiente a su instalación de PHP:
     - Para PHP 8.2 x64: `phalcon_x64_php8.2_5.9.3.dll`

2. **Instalar la Extensión**
   - Copie el archivo DLL descargado a la carpeta de extensiones de PHP:
     ```
     C:\xampp\php\ext\
     ```
   - Abra el archivo de configuración de PHP:
     ```
     C:\xampp\php\php.ini
     ```
   - Agregue la siguiente línea al final de la sección de extensiones:
     ```ini
     extension=phalcon
     ```

3. **Habilitar Extensiones Adicionales**
   - En el mismo archivo `php.ini`, asegúrese de que las siguientes extensiones estén habilitadas (sin `;` al inicio):
     ```ini
     extension=pdo_pgsql
     extension=pgsql
     extension=openssl
     extension=curl
     extension=mbstring
     extension=fileinfo
     ```

4. **Reiniciar Apache**
   - En el Panel de Control de XAMPP, detenga Apache
   - Inicie Apache nuevamente
   - Verifique que Phalcon esté cargado creando un archivo `phpinfo.php` en `C:\xampp\htdocs\`:
     ```php
     <?php phpinfo(); ?>
     ```
   - Navegue a `http://localhost/phpinfo.php` y busque "Phalcon" en la salida

### Paso 4: Configuración de PHP para PostgreSQL

1. **Verificar Extensiones PostgreSQL**
   - En la página de `phpinfo()`, verifique que aparezcan:
     - `pdo_pgsql`
     - `pgsql`

2. **Configurar Variables de Entorno (Opcional)**
   - Agregue la ruta de PostgreSQL al PATH del sistema:
     ```
     C:\Program Files\PostgreSQL\14\bin
     ```

### Paso 5: Configuración de Permisos y Seguridad

1. **Configurar Permisos de Carpetas**
   - Asegúrese de que la carpeta `C:\xampp\htdocs\` tenga permisos de lectura y escritura
   - Configure permisos específicos para las carpetas del proyecto:
     - `cache/` - Lectura y escritura
     - `logs/` - Lectura y escritura
     - `uploads/` - Lectura y escritura

2. **Configuración de Apache**
   - Edite el archivo: `C:\xampp\apache\conf\httpd.conf`
   - Asegúrese de que el módulo `mod_rewrite` esté habilitado:
     ```apache
     LoadModule rewrite_module modules/mod_rewrite.so
     ```
   - Configure AllowOverride para permitir archivos .htaccess:
     ```apache
     <Directory "C:/xampp/htdocs">
         AllowOverride All
         Require all granted
     </Directory>
     ```

3. **Reiniciar Servicios**
   - Reinicie Apache desde el Panel de Control de XAMPP
   - Verifique que no haya errores en los logs de Apache

### Solución de Problemas Comunes

#### Error: "Phalcon extension not loaded"
- Verifique que el archivo DLL esté en la carpeta correcta
- Asegúrese de que la línea `extension=phalcon` esté en `php.ini`
- Reinicie Apache completamente

#### Error: "Could not find driver (PostgreSQL)"
- Verifique que las extensiones `pdo_pgsql` y `pgsql` estén habilitadas
- Reinstale PostgreSQL si es necesario
- Verifique que PostgreSQL esté ejecutándose como servicio

#### Error: "Permission denied"
- Ejecute XAMPP como administrador
- Verifique permisos de carpetas
- Desactive temporalmente el antivirus para la instalación

#### Puerto 80 en uso
- Detenga IIS si está ejecutándose
- Cambie el puerto de Apache en la configuración
- Use el comando `netstat -ano | findstr :80` para identificar procesos que usan el puerto


## Configuración del Proyecto

### Paso 1: Extracción del Proyecto

1. **Extraer Archivos**
   - Extraiga el archivo ZIP del proyecto en la carpeta de XAMPP:
     ```
     C:\xampp\htdocs\tienda_web_phalcon\
     ```
   - Asegúrese de que la estructura de carpetas sea correcta:
     ```
     tienda_web_phalcon/
     ├── app/
     │   ├── config/
     │   ├── controllers/
     │   ├── models/
     │   ├── views/
     │   └── library/
     ├── public/
     │   ├── css/
     │   ├── js/
     │   ├── img/
     │   └── index.php
     ├── sql/
     ├── cache/
     ├── logs/
     └── composer.json
     ```

2. **Configurar Permisos**
   - Asegúrese de que las siguientes carpetas tengan permisos de escritura:
     - `cache/`
     - `logs/`
     - `public/uploads/` (créela si no existe)

### Paso 2: Configuración de Variables de Entorno

1. **Crear Archivo de Configuración**
   - Copie el archivo `.env.example` y renómbrelo a `.env`
   - Edite el archivo `.env` con la configuración específica de su entorno:

   ```env
   # Configuración de la base de datos
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=tienda_inventario
   DB_USER=postgres
   DB_PASS=su_contraseña_postgresql

   # Configuración de la aplicación
   APP_ENV=development
   APP_DEBUG=true
   APP_URL=http://localhost/tienda_web_phalcon
   APP_TIMEZONE=America/Bogota

   # Configuración de seguridad
   SECURITY_SALT=TiendaWeb2024!@#$%^&*()
   JWT_SECRET=mi_clave_secreta_jwt_muy_segura_2024

   # Configuración de email
   MAIL_DRIVER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=cerebritonjp@gmail.com
   MAIL_PASSWORD=blbxdgttcgogmzmq
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=cerebritonjp@gmail.com
   MAIL_FROM_NAME="Mi Tienda Online"

   # Configuración de PayPal
   PAYPAL_CLIENT_ID=AdvBS1xwvjtsmoLGCmum6w7vb3tcpbFcG2iPw6aI9vQu6MIicJQkJz2tX80l7yEDXY_wOXjkoMw0OGe2
   PAYPAL_SECRET=ELau1B6EGRxFCyXrX1y5zOeguZNRG8OmHVcdoNdJOKfZeBbShSfCfz3qp4SDG_JAbFVtYE2qfm89HOxd
   PAYPAL_MODE=sandbox

   # Configuración de la tienda
   STORE_NAME="Mi Tienda Online"
   STORE_CURRENCY=COP
   TAX_RATE=0.19
   STOCK_ALERT_THRESHOLD=5
   ```

2. **Configurar URL Base**
   - Edite el archivo `app/config/config.php`
   - Asegúrese de que la URL base sea correcta:
     ```php
     'baseUri' => '/tienda_web_phalcon/',
     ```

### Paso 3: Instalación de Dependencias

1. **Instalar Composer (si no está instalado)**
   - Descargue Composer desde: https://getcomposer.org/download/
   - Ejecute el instalador para Windows
   - Verifique la instalación ejecutando en CMD: `composer --version`

2. **Instalar Dependencias del Proyecto**
   - Abra una terminal (CMD o PowerShell) como administrador
   - Navegue a la carpeta del proyecto:
     ```cmd
     cd C:\xampp\htdocs\tienda_web_phalcon
     ```
   - Ejecute Composer:
     ```cmd
     composer install
     ```

## Inicialización de la Base de Datos

### Paso 1: Crear la Base de Datos

1. **Conectar a PostgreSQL**
   - Abra pgAdmin 4
   - Conéctese al servidor PostgreSQL local
   - Clic derecho en "Databases" → "Create" → "Database..."
   - Nombre: `tienda_inventario`
   - Owner: `postgres`
   - Encoding: `UTF8`

2. **Verificar Conexión desde PHP**
   - Cree un archivo de prueba `test_db.php` en la carpeta `public/`:
     ```php
     <?php
     try {
         $pdo = new PDO('pgsql:host=localhost;port=5432;dbname=tienda_inventario', 'postgres', 'su_contraseña');
         echo "Conexión exitosa a PostgreSQL";
     } catch (PDOException $e) {
         echo "Error de conexión: " . $e->getMessage();
     }
     ?>
     ```
   - Navegue a `http://localhost/tienda_web_phalcon/public/test_db.php`
   - Debería ver "Conexión exitosa a PostgreSQL"

### Paso 2: Ejecutar Scripts de Base de Datos

1. **Crear Esquema de Tablas**
   - En pgAdmin 4, abra la base de datos `tienda_inventario`
   - Clic derecho en la base de datos → "Query Tool"
   - Abra el archivo `sql/schema.sql` y copie todo el contenido
   - Pegue el contenido en el Query Tool y ejecute (F5)

2. **Insertar Datos Iniciales**
   - En el mismo Query Tool, limpie el contenido anterior
   - Abra el archivo `sql/datos_iniciales.sql` y copie todo el contenido
   - Pegue el contenido y ejecute (F5)

3. **Verificar Datos**
   - Ejecute la siguiente consulta para verificar que los datos se insertaron correctamente:
     ```sql
     SELECT * FROM usuarios;
     SELECT * FROM roles;
     SELECT * FROM categorias;
     SELECT * FROM estados_pedido;
     ```

### Paso 3: Configurar Permisos de Base de Datos

1. **Crear Usuario de Aplicación (Opcional pero Recomendado)**
   ```sql
   CREATE USER tienda_user WITH PASSWORD 'contraseña_segura';
   GRANT ALL PRIVILEGES ON DATABASE tienda_inventario TO tienda_user;
   GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO tienda_user;
   GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO tienda_user;
   ```

2. **Actualizar Configuración**
   - Si creó un usuario específico, actualice el archivo `.env`:
     ```env
     DB_USER=tienda_user
     DB_PASS=contraseña_segura
     ```

## Configuración de PayPal

### Paso 1: Configuración de Cuenta Sandbox

1. **Crear Cuenta de Desarrollador**
   - Visite: https://developer.paypal.com/
   - Inicie sesión o cree una cuenta de desarrollador
   - Navegue a "My Apps & Credentials"

2. **Crear Aplicación Sandbox**
   - Clic en "Create App"
   - Nombre de la aplicación: "Tienda Web Inventario"
   - Seleccione "Sandbox" como entorno
   - Seleccione una cuenta de negocio sandbox

3. **Obtener Credenciales**
   - Una vez creada la aplicación, obtendrá:
     - Client ID
     - Client Secret
   - Estas credenciales ya están configuradas en el sistema

### Paso 2: Configurar URLs de Retorno

1. **URLs de la Aplicación**
   - En la configuración de la aplicación PayPal, configure:
     - Return URL: `http://localhost/tienda_web_phalcon/paypal/success`
     - Cancel URL: `http://localhost/tienda_web_phalcon/paypal/cancel`

2. **Verificar Configuración**
   - Las credenciales ya están configuradas en `app/config/config.php`
   - Para producción, cambie `mode` de `sandbox` a `live`

## Configuración de Email

### Configuración SMTP

El sistema está preconfigurado para usar Gmail SMTP con las siguientes credenciales:
- **Host**: smtp.gmail.com
- **Puerto**: 587
- **Usuario**: cerebritonjp@gmail.com
- **Contraseña de aplicación**: blbxdgttcgogmzmq

### Configurar Email Personalizado

Para usar su propio email:

1. **Gmail**
   - Habilite la verificación en dos pasos
   - Genere una contraseña de aplicación
   - Actualice las credenciales en el archivo `.env`

2. **Otros Proveedores**
   - Actualice la configuración SMTP en `.env`:
     ```env
     MAIL_HOST=smtp.su-proveedor.com
     MAIL_PORT=587
     MAIL_USERNAME=su-email@dominio.com
     MAIL_PASSWORD=su-contraseña
     ```

## Pruebas del Sistema

### Paso 1: Verificar Instalación

1. **Acceder al Sistema**
   - Navegue a: `http://localhost/tienda_web_phalcon/`
   - Debería ver la página principal de la tienda

2. **Probar Autenticación**
   - Navegue a: `http://localhost/tienda_web_phalcon/login`
   - Pruebe con los usuarios de demostración:
     - **Admin**: demoadmin@tienda.com / Demo123c
     - **Empleado**: demoempleado@tienda.com / Demo123c
     - **Cliente**: democliente@tienda.com / Demo123c

### Paso 2: Verificar Funcionalidades

1. **Panel Administrativo**
   - Inicie sesión como administrador
   - Verifique acceso a:
     - Dashboard
     - Gestión de productos
     - Gestión de usuarios
     - Reportes
     - Inventario

2. **Funcionalidades del Cliente**
   - Inicie sesión como cliente
   - Verifique:
     - Navegación de productos
     - Carrito de compras
     - Proceso de checkout
     - Historial de compras

### Paso 3: Pruebas de Integración

1. **Prueba de PayPal**
   - Realice una compra de prueba
   - Verifique redirección a PayPal sandbox
   - Complete el pago con cuenta de prueba

2. **Prueba de Email**
   - Registre un nuevo usuario
   - Verifique que se envíe email de confirmación
   - Pruebe recuperación de contraseña

### Logs y Depuración

1. **Ubicación de Logs**
   - Logs de la aplicación: `logs/application.log`
   - Logs de Apache: `C:\xampp\apache\logs\error.log`
   - Logs de PostgreSQL: Configurados en pgAdmin

2. **Habilitar Modo Debug**
   - En `.env`, configure: `APP_DEBUG=true`
   - Esto mostrará errores detallados en pantalla

3. **Verificar Configuración PHP**
   - Navegue a: `http://localhost/tienda_web_phalcon/public/phpinfo.php`
   - Verifique que todas las extensiones estén cargadas


## Solución de Problemas

### Problemas Comunes de Instalación

#### Error: "Class 'Phalcon\Mvc\Application' not found"
**Causa**: Phalcon no está instalado correctamente o no está cargado.

**Solución**:
1. Verifique que el archivo `phalcon.dll` esté en `C:\xampp\php\ext\`
2. Confirme que la línea `extension=phalcon` esté en `php.ini`
3. Reinicie Apache completamente
4. Verifique en `phpinfo()` que Phalcon aparezca como extensión cargada

#### Error: "SQLSTATE[08006] [7] could not connect to server"
**Causa**: PostgreSQL no está ejecutándose o la configuración de conexión es incorrecta.

**Solución**:
1. Verifique que PostgreSQL esté ejecutándose como servicio
2. Confirme las credenciales en el archivo `.env`
3. Pruebe la conexión desde pgAdmin
4. Verifique que el puerto 5432 no esté bloqueado por firewall

#### Error: "Permission denied" en carpetas cache/ o logs/
**Causa**: Permisos insuficientes en las carpetas del sistema.

**Solución**:
1. Ejecute XAMPP como administrador
2. Configure permisos de escritura en las carpetas:
   ```cmd
   icacls "C:\xampp\htdocs\tienda_web_phalcon\cache" /grant Everyone:F
   icacls "C:\xampp\htdocs\tienda_web_phalcon\logs" /grant Everyone:F
   ```

#### Error: "Composer command not found"
**Causa**: Composer no está instalado o no está en el PATH del sistema.

**Solución**:
1. Descargue e instale Composer desde getcomposer.org
2. Agregue Composer al PATH del sistema
3. Reinicie la terminal y pruebe `composer --version`

### Problemas de Configuración

#### PayPal retorna error "Invalid credentials"
**Causa**: Credenciales de PayPal incorrectas o aplicación mal configurada.

**Solución**:
1. Verifique las credenciales en PayPal Developer Dashboard
2. Confirme que esté usando el entorno correcto (sandbox/live)
3. Verifique las URLs de retorno configuradas
4. Revise los logs de la aplicación para detalles del error

#### Emails no se envían
**Causa**: Configuración SMTP incorrecta o credenciales inválidas.

**Solución**:
1. Verifique las credenciales SMTP en `.env`
2. Para Gmail, asegúrese de usar contraseña de aplicación
3. Verifique que el puerto 587 no esté bloqueado
4. Pruebe con un cliente de email para confirmar credenciales

#### Productos no aparecen en la tienda
**Causa**: Datos no insertados correctamente o productos inactivos.

**Solución**:
1. Verifique que los datos iniciales se insertaron correctamente
2. Confirme que los productos estén marcados como activos
3. Revise las categorías y que estén activas
4. Verifique permisos de la base de datos

### Problemas de Rendimiento

#### Página carga lentamente
**Solución**:
1. Habilite el cache de Phalcon en producción
2. Optimice consultas de base de datos
3. Configure compresión en Apache
4. Verifique logs de errores para consultas lentas

#### Errores de memoria PHP
**Solución**:
1. Aumente `memory_limit` en `php.ini`:
   ```ini
   memory_limit = 256M
   ```
2. Optimice consultas que retornan muchos registros
3. Implemente paginación en listados grandes

## Mantenimiento

### Copias de Seguridad

#### Base de Datos
1. **Backup Manual**:
   ```cmd
   pg_dump -h localhost -U postgres -d tienda_inventario > backup_tienda.sql
   ```

2. **Backup Automatizado**:
   - Configure una tarea programada en Windows
   - Ejecute el script de backup diariamente
   - Almacene backups en ubicación segura

3. **Restauración**:
   ```cmd
   psql -h localhost -U postgres -d tienda_inventario < backup_tienda.sql
   ```

#### Archivos del Sistema
1. **Carpetas Importantes**:
   - `app/` - Código de la aplicación
   - `public/uploads/` - Archivos subidos por usuarios
   - `.env` - Configuración del entorno

2. **Backup de Archivos**:
   - Copie regularmente la carpeta completa del proyecto
   - Use herramientas como 7-Zip para comprimir
   - Almacene en ubicación externa (nube, disco externo)

### Actualizaciones

#### Actualizar Phalcon
1. Descargue la nueva versión desde phalcon.io
2. Reemplace el archivo DLL en `php\ext\`
3. Reinicie Apache
4. Verifique compatibilidad con el código existente

#### Actualizar PostgreSQL
1. Realice backup completo antes de actualizar
2. Descargue nueva versión desde postgresql.org
3. Ejecute el instalador (mantendrá datos existentes)
4. Verifique conectividad después de la actualización

#### Actualizar Dependencias PHP
```cmd
composer update
```

### Monitoreo

#### Logs del Sistema
1. **Revisar Regularmente**:
   - `logs/application.log` - Errores de la aplicación
   - `logs/access.log` - Accesos al sistema
   - Apache error logs - Errores del servidor web

2. **Configurar Alertas**:
   - Configure notificaciones por email para errores críticos
   - Use herramientas de monitoreo como Nagios o Zabbix

#### Métricas de Rendimiento
1. **Base de Datos**:
   - Monitor conexiones activas
   - Tiempo de respuesta de consultas
   - Uso de espacio en disco

2. **Servidor Web**:
   - Tiempo de respuesta de páginas
   - Uso de memoria PHP
   - Número de requests por minuto

### Seguridad

#### Actualizaciones de Seguridad
1. **Mantener Actualizado**:
   - XAMPP/Apache
   - PHP y extensiones
   - PostgreSQL
   - Framework Phalcon

2. **Configuración Segura**:
   - Cambiar contraseñas por defecto
   - Deshabilitar funciones PHP peligrosas
   - Configurar HTTPS en producción
   - Implementar rate limiting

#### Auditoría
1. **Revisar Logs de Acceso**:
   - Intentos de login fallidos
   - Accesos a páginas administrativas
   - Patrones de tráfico inusuales

2. **Verificar Integridad**:
   - Checksums de archivos críticos
   - Permisos de archivos y carpetas
   - Configuración de base de datos

### Optimización

#### Base de Datos
1. **Índices**:
   ```sql
   -- Crear índices para mejorar rendimiento
   CREATE INDEX idx_productos_categoria ON productos(categoria_id);
   CREATE INDEX idx_pedidos_cliente ON pedidos(cliente_id);
   CREATE INDEX idx_pedidos_fecha ON pedidos(fecha_pedido);
   ```

2. **Mantenimiento**:
   ```sql
   -- Ejecutar periódicamente
   VACUUM ANALYZE;
   REINDEX DATABASE tienda_inventario;
   ```

#### Aplicación
1. **Cache**:
   - Habilitar cache de vistas en producción
   - Implementar cache de consultas frecuentes
   - Usar cache de sesiones en Redis/Memcached

2. **Optimización de Código**:
   - Minimizar consultas N+1
   - Usar eager loading para relaciones
   - Implementar paginación en listados

## Contacto y Soporte

### Información del Sistema
- **Versión**: 1.0
- **Framework**: Phalcon PHP 5.9.3
- **Base de Datos**: PostgreSQL 14+
- **Desarrollado por**: Manus AI

### Documentación Adicional
- Manual de Usuario: `MANUAL_USUARIO.md`
- Documentación de API: `API_DOCUMENTATION.md`
- Guía de Desarrollo: `DEVELOPMENT_GUIDE.md`

### Recursos Útiles
- **Phalcon Documentation**: https://docs.phalcon.io/
- **PostgreSQL Documentation**: https://www.postgresql.org/docs/
- **PayPal Developer**: https://developer.paypal.com/
- **XAMPP Documentation**: https://www.apachefriends.org/docs/

---

**Nota**: Este manual cubre la instalación básica del sistema. Para configuraciones avanzadas o implementación en producción, consulte la documentación adicional o contacte al equipo de desarrollo.

**Última actualización**: Diciembre 2024

