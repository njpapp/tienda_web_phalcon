<?php

use Phalcon\Mvc\Router;

$router = new Router();

// Remover trailing slashes
$router->removeExtraSlashes(true);

// Ruta por defecto
$router->add('/', [
    'controller' => 'index',
    'action'     => 'index'
]);

// Rutas de autenticación
$router->addGet('/login', [
    'controller' => 'auth',
    'action'     => 'login'
]);

$router->addPost('/login', [
    'controller' => 'auth',
    'action'     => 'authenticate'
]);

$router->addGet('/logout', [
    'controller' => 'auth',
    'action'     => 'logout'
]);

$router->addGet('/register', [
    'controller' => 'auth',
    'action'     => 'register'
]);

$router->addPost('/register', [
    'controller' => 'auth',
    'action'     => 'create'
]);

// Rutas del panel de cliente
$router->addGet('/cliente', [
    'controller' => 'cliente',
    'action'     => 'dashboard'
]);

$router->addGet('/cliente/perfil', [
    'controller' => 'cliente',
    'action'     => 'perfil'
]);

$router->addPost('/cliente/perfil', [
    'controller' => 'cliente',
    'action'     => 'actualizarPerfil'
]);

$router->addGet('/cliente/compras', [
    'controller' => 'cliente',
    'action'     => 'compras'
]);

$router->addGet('/cliente/compras/{id:[0-9]+}', [
    'controller' => 'cliente',
    'action'     => 'detalleCompra'
]);

$router->addGet('/cliente/tarjetas', [
    'controller' => 'cliente',
    'action'     => 'tarjetas'
]);

$router->addPost('/cliente/tarjetas', [
    'controller' => 'cliente',
    'action'     => 'agregarTarjeta'
]);

$router->addDelete('/cliente/tarjetas/{id:[0-9]+}', [
    'controller' => 'cliente',
    'action'     => 'eliminarTarjeta'
]);

// Rutas del panel administrativo
$router->addGet('/admin', [
    'controller' => 'admin',
    'action'     => 'dashboard'
]);

$router->addGet('/admin/usuarios', [
    'controller' => 'admin',
    'action'     => 'usuarios'
]);

$router->addGet('/admin/usuarios/crear', [
    'controller' => 'admin',
    'action'     => 'crearUsuario'
]);

$router->addPost('/admin/usuarios/crear', [
    'controller' => 'admin',
    'action'     => 'guardarUsuario'
]);

$router->addGet('/admin/usuarios/{id:[0-9]+}', [
    'controller' => 'admin',
    'action'     => 'editarUsuario'
]);

$router->addPost('/admin/usuarios/{id:[0-9]+}', [
    'controller' => 'admin',
    'action'     => 'actualizarUsuario'
]);

// Rutas de productos
$router->addGet('/admin/productos', [
    'controller' => 'productos',
    'action'     => 'index'
]);

$router->addGet('/admin/productos/crear', [
    'controller' => 'productos',
    'action'     => 'crear'
]);

$router->addPost('/admin/productos/crear', [
    'controller' => 'productos',
    'action'     => 'guardar'
]);

$router->addGet('/admin/productos/{id:[0-9]+}', [
    'controller' => 'productos',
    'action'     => 'editar'
]);

$router->addPost('/admin/productos/{id:[0-9]+}', [
    'controller' => 'productos',
    'action'     => 'actualizar'
]);

$router->addDelete('/admin/productos/{id:[0-9]+}', [
    'controller' => 'productos',
    'action'     => 'eliminar'
]);

// Rutas de inventario
$router->addGet('/admin/inventario', [
    'controller' => 'inventario',
    'action'     => 'index'
]);

$router->addGet('/admin/inventario/movimientos', [
    'controller' => 'inventario',
    'action'     => 'movimientos'
]);

$router->addPost('/admin/inventario/ajustar', [
    'controller' => 'inventario',
    'action'     => 'ajustar'
]);

$router->addGet('/admin/inventario/alertas', [
    'controller' => 'inventario',
    'action'     => 'alertas'
]);

// Rutas de ventas
$router->addGet('/admin/ventas', [
    'controller' => 'ventas',
    'action'     => 'index'
]);

$router->addGet('/admin/ventas/{id:[0-9]+}', [
    'controller' => 'ventas',
    'action'     => 'detalle'
]);

$router->addPost('/admin/ventas/{id:[0-9]+}/estado', [
    'controller' => 'ventas',
    'action'     => 'cambiarEstado'
]);

$router->addGet('/admin/reportes', [
    'controller' => 'reportes',
    'action'     => 'index'
]);

$router->addGet('/admin/reportes/ventas', [
    'controller' => 'reportes',
    'action'     => 'ventas'
]);

$router->addGet('/admin/reportes/inventario', [
    'controller' => 'reportes',
    'action'     => 'inventario'
]);

// Rutas de la tienda (frontend)
$router->addGet('/tienda', [
    'controller' => 'tienda',
    'action'     => 'index'
]);

$router->addGet('/tienda/categoria/{id:[0-9]+}', [
    'controller' => 'tienda',
    'action'     => 'categoria'
]);

$router->addGet('/tienda/producto/{id:[0-9]+}', [
    'controller' => 'tienda',
    'action'     => 'producto'
]);

$router->addPost('/tienda/carrito/agregar', [
    'controller' => 'carrito',
    'action'     => 'agregar'
]);

$router->addGet('/carrito', [
    'controller' => 'carrito',
    'action'     => 'ver'
]);

$router->addPost('/carrito/actualizar', [
    'controller' => 'carrito',
    'action'     => 'actualizar'
]);

$router->addDelete('/carrito/eliminar/{id:[0-9]+}', [
    'controller' => 'carrito',
    'action'     => 'eliminar'
]);

$router->addGet('/checkout', [
    'controller' => 'checkout',
    'action'     => 'index'
]);

$router->addPost('/checkout/procesar', [
    'controller' => 'checkout',
    'action'     => 'procesar'
]);

// Rutas de API
$router->addGet('/api/productos/buscar', [
    'controller' => 'api',
    'action'     => 'buscarProductos'
]);

$router->addGet('/api/inventario/stock/{id:[0-9]+}', [
    'controller' => 'api',
    'action'     => 'obtenerStock'
]);

$router->addPost('/api/inventario/movimiento', [
    'controller' => 'api',
    'action'     => 'registrarMovimiento'
]);

// Rutas de categorías
$router->addGet('/admin/categorias', [
    'controller' => 'categorias',
    'action'     => 'index'
]);

$router->addGet('/admin/categorias/crear', [
    'controller' => 'categorias',
    'action'     => 'crear'
]);

$router->addPost('/admin/categorias/crear', [
    'controller' => 'categorias',
    'action'     => 'guardar'
]);

$router->addGet('/admin/categorias/{id:[0-9]+}', [
    'controller' => 'categorias',
    'action'     => 'editar'
]);

$router->addPost('/admin/categorias/{id:[0-9]+}', [
    'controller' => 'categorias',
    'action'     => 'actualizar'
]);

// Rutas de proveedores
$router->addGet('/admin/proveedores', [
    'controller' => 'proveedores',
    'action'     => 'index'
]);

$router->addGet('/admin/proveedores/crear', [
    'controller' => 'proveedores',
    'action'     => 'crear'
]);

$router->addPost('/admin/proveedores/crear', [
    'controller' => 'proveedores',
    'action'     => 'guardar'
]);

$router->addGet('/admin/proveedores/{id:[0-9]+}', [
    'controller' => 'proveedores',
    'action'     => 'editar'
]);

$router->addPost('/admin/proveedores/{id:[0-9]+}', [
    'controller' => 'proveedores',
    'action'     => 'actualizar'
]);

// Ruta para manejar errores 404
$router->notFound([
    'controller' => 'errors',
    'action'     => 'show404'
]);

return $router;

