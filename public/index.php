<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;
use Phalcon\Events\Manager as EventsManager;
use App\Library\AuthMiddleware;

// Definir constantes de la aplicación
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

try {
    // Autoloader
    require_once BASE_PATH . '/vendor/autoload.php';

    // Crear el contenedor de dependencias
    $di = new FactoryDefault();

    // Cargar servicios
    include APP_PATH . '/config/services.php';

    // Crear la aplicación
    $application = new Application($di);

    // Configurar el gestor de eventos
    $eventsManager = new EventsManager();

    // Registrar el middleware de autenticación
    $authMiddleware = new AuthMiddleware();
    $authMiddleware->setDI($di);
    $eventsManager->attach('dispatch:beforeExecuteRoute', $authMiddleware);

    // Asignar el gestor de eventos al dispatcher
    $dispatcher = $di->getDispatcher();
    $dispatcher->setEventsManager($eventsManager);

    // Configurar el router
    $router = include APP_PATH . '/config/routes.php';
    $di->setShared('router', $router);

    // Manejar la petición
    $response = $application->handle(
        $_SERVER["REQUEST_URI"]
    );

    $response->send();

} catch (\Exception $e) {
    // Log del error
    if (isset($di) && $di->has('logger')) {
        $di->getLogger()->error($e->getMessage() . ' - ' . $e->getTraceAsString());
    }

    // Mostrar error según el entorno
    if (isset($di) && $di->has('config')) {
        $config = $di->getConfig();
        if ($config->debug) {
            echo '<h1>Error en la aplicación</h1>';
            echo '<p><strong>Mensaje:</strong> ' . $e->getMessage() . '</p>';
            echo '<p><strong>Archivo:</strong> ' . $e->getFile() . ':' . $e->getLine() . '</p>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
        } else {
            echo '<h1>Error interno del servidor</h1>';
            echo '<p>Ha ocurrido un error inesperado. Por favor intenta más tarde.</p>';
        }
    } else {
        echo '<h1>Error de configuración</h1>';
        echo '<p>No se pudo cargar la configuración de la aplicación.</p>';
    }
}

