<?php

declare(strict_types=1);

use App\Env;

return [
    'name' => 'AutoSchedule',

    'env' => Env::string('APP_ENV', 'production'),

    'debug' => Env::bool('APP_DEBUG', false),

    'timezone' => Env::string('APP_TIMEZONE', 'America/Sao_Paulo'),

    'database' => [
        'driver' => Env::string('DB_DRIVER', 'pgsql'),
        'host' => Env::string('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 5432),
        'database' => Env::string('DB_DATABASE', 'autoschedule'),
        'username' => Env::string('DB_USERNAME', 'pgsql'),
        'password' => Env::string('DB_PASSWORD', 'password'),
        'app_username' => Env::string('DB_APP_USERNAME', 'autoschedule_app'),
        'app_password' => Env::string('DB_APP_PASSWORD', 'changeme'),
    ],
];
