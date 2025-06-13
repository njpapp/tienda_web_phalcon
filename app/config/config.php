<?php

use Phalcon\Config;

return new Config([
    'database' => [
        'adapter'  => 'Postgresql',
        'host'     => 'localhost',
        'username' => 'postgres',
        'password' => 'password',
        'dbname'   => 'tienda_inventario',
        'charset'  => 'utf8',
        'port'     => 5432,
        'options'  => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    
    'application' => [
        'appDir'         => APP_PATH . '/',
        'controllersDir' => APP_PATH . '/controllers/',
        'modelsDir'      => APP_PATH . '/models/',
        'viewsDir'       => APP_PATH . '/views/',
        'libraryDir'     => APP_PATH . '/library/',
        'cacheDir'       => BASE_PATH . '/cache/',
        'logsDir'        => BASE_PATH . '/logs/',
        'baseUri'        => '/',
    ],
    
    'session' => [
        'adapter'     => 'Files',
        'options'     => [
            'uniqueId'   => 'tienda_web_',
            'lifetime'   => 7200, // 2 horas
            'path'       => BASE_PATH . '/cache/sessions/',
            'httpOnly'   => true,
            'secure'     => false, // cambiar a true en producción con HTTPS
            'sameSite'   => 'Lax'
        ]
    ],
    
    'security' => [
        'salt'           => 'TiendaWeb2024!@#$%^&*()',
        'workFactor'     => 12,
        'tokenExpiry'    => 3600, // 1 hora
        'maxLoginAttempts' => 5,
        'lockoutTime'    => 900, // 15 minutos
    ],
    
    'paypal' => [
        'client_id' => 'AdvBS1xwvjtsmoLGCmum6w7vb3tcpbFcG2iPw6aI9vQu6MIicJQkJz2tX80l7yEDXY_wOXjkoMw0OGe2',
        'secret' => 'ELau1B6EGRxFCyXrX1y5zOeguZNRG8OmHVcdoNdJOKfZeBbShSfCfz3qp4SDG_JAbFVtYE2qfm89HOxd',
        'mode' => 'sandbox', // sandbox o live
        'currency' => 'USD',
        'return_url' => '/checkout/paypal/success',
        'cancel_url' => '/checkout/paypal/cancel'
    ],
    
    'mail' => [
        'driver'     => 'smtp',
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'cerebritonjp@gmail.com',
        'password'   => 'blbxdgttcgogmzmq',
        'from'       => [
            'email' => 'cerebritonjp@gmail.com',
            'name'  => 'Mi Tienda Online'
        ]
    ],
    
    'cache' => [
        'adapter'  => 'File',
        'options'  => [
            'cacheDir' => BASE_PATH . '/cache/data/',
            'lifetime' => 3600
        ]
    ],
    
    'logger' => [
        'path'     => BASE_PATH . '/logs/',
        'format'   => '[%date%][%type%] %message%',
        'date'     => 'Y-m-d H:i:s',
        'logLevel' => \Phalcon\Logger::DEBUG,
        'filename' => 'application.log'
    ],
    
    'pagination' => [
        'limit' => 20,
        'maxLimit' => 100
    ],
    
    'upload' => [
        'maxSize'      => 5242880, // 5MB
        'allowedTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'path'         => BASE_PATH . '/public/uploads/',
        'url'          => '/uploads/'
    ],
    
    'api' => [
        'version'      => 'v1',
        'rateLimit'    => 1000, // requests per hour
        'tokenExpiry'  => 86400, // 24 horas
    ],
    
    'environment' => 'development', // development, testing, production
    
    'debug' => true,
    
    'timezone' => 'America/Bogota',
    
    'locale' => 'es_MX'
]);

