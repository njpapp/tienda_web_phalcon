<?php

use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Di\FactoryDefault;
use Phalcon\Session\Manager;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Flash\Direct as Flash;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Url;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Cache;
use Phalcon\Cache\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line;
use Phalcon\Security;
use Phalcon\Crypt;
use Phalcon\Http\Response\Cookies;

$di = new FactoryDefault();

/**
 * Configuración de la base de datos
 */
$di->setShared('db', function () {
    $config = $this->getConfig();
    
    return new Postgresql([
        'host'     => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname'   => $config->database->dbname,
        'port'     => $config->database->port,
        'options'  => $config->database->options->toArray()
    ]);
});

/**
 * Configuración de sesiones
 */
$di->setShared('session', function () {
    $config = $this->getConfig();
    
    $session = new Manager();
    $files = new Stream([
        'savePath' => $config->session->options->path,
    ]);
    
    $session->setAdapter($files);
    $session->start();
    
    return $session;
});

/**
 * Configuración de URL
 */
$di->setShared('url', function () {
    $config = $this->getConfig();
    
    $url = new Url();
    $url->setBaseUri($config->application->baseUri);
    
    return $url;
});

/**
 * Configuración de vistas
 */
$di->setShared('view', function () {
    $config = $this->getConfig();
    
    $view = new View();
    $view->setViewsDir($config->application->viewsDir);
    
    $view->registerEngines([
        '.volt' => function ($view) {
            $config = $this->getConfig();
            
            $volt = new Volt($view, $this);
            $volt->setOptions([
                'path' => $config->application->cacheDir . 'volt/',
                'separator' => '_'
            ]);
            
            return $volt;
        },
        '.phtml' => \Phalcon\Mvc\View\Engine\Php::class
    ]);
    
    return $view;
});

/**
 * Configuración de Flash Messages
 */
$di->set('flash', function () {
    $flash = new Flash([
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning'
    ]);
    
    return $flash;
});

$di->set('flashSession', function () {
    $flash = new FlashSession([
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning'
    ]);
    
    return $flash;
});

/**
 * Configuración de Cache
 */
$di->setShared('cache', function () {
    $config = $this->getConfig();
    
    $serializerFactory = new SerializerFactory();
    $adapterFactory = new AdapterFactory($serializerFactory);
    
    $options = [
        'defaultSerializer' => 'Json',
        'lifetime'          => $config->cache->options->lifetime,
        'storageDir'        => $config->cache->options->cacheDir,
    ];
    
    $adapter = $adapterFactory->newInstance('file', $options);
    
    return new Cache($adapter);
});

/**
 * Configuración de Logger
 */
$di->setShared('logger', function () {
    $config = $this->getConfig();
    
    $formatter = new Line($config->logger->format, $config->logger->date);
    $logger = new Logger(
        'messages',
        [
            'main' => new FileAdapter($config->logger->path . $config->logger->filename)
        ]
    );
    
    $logger->getAdapter('main')->setFormatter($formatter);
    
    return $logger;
});

/**
 * Configuración de Security
 */
$di->setShared('security', function () {
    $config = $this->getConfig();
    
    $security = new Security();
    $security->setWorkFactor($config->security->workFactor);
    
    return $security;
});

/**
 * Configuración de Crypt
 */
$di->setShared('crypt', function () {
    $config = $this->getConfig();
    
    $crypt = new Crypt();
    $crypt->setKey($config->security->salt);
    
    return $crypt;
});

/**
 * Configuración de Cookies
 */
$di->setShared('cookies', function () {
    $cookies = new Cookies();
    $cookies->useEncryption(true);
    
    return $cookies;
});

/**
 * Configuración personalizada
 */
$di->setShared('config', function () {
    return include APP_PATH . '/config/config.php';
});

/**
 * Servicio de autenticación personalizado
 */
$di->setShared('auth', function () {
    return new \App\Library\Auth();
});

/**
 * Servicio de utilidades
 */
$di->setShared('utils', function () {
    return new \App\Library\Utils();
});

/**
 * Servicio de inventario
 */
$di->setShared('inventario', function () {
    return new \App\Library\InventarioService();
});

/**
 * Servicio de ventas
 */
$di->setShared('ventas', function () {
    return new \App\Library\VentasService();
});

return $di;

