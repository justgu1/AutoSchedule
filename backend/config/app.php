<?php

declare(strict_types=1);

return [
    'name' => 'AutoSchedule',

    'env' => getenv('APP_ENV') ?: 'production',

    'debug' => filter_var(
        getenv('APP_DEBUG') ?: false,
        FILTER_VALIDATE_BOOL
    ),

    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo',

    'database' => [
        'driver' => getenv('DB_DRIVER') ?: 'pgsql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 5432),
        'database' => getenv('DB_DATABASE') ?: 'autoschedule',
        'username' => getenv('DB_USERNAME') ?: 'pgsql',
        'password' => getenv('DB_PASSWORD') ?: 'password',
        'app_username' => getenv('DB_APP_USERNAME') ?: 'autoschedule_app',
        'app_password' => getenv('DB_APP_PASSWORD') ?: 'changeme',
    ],
];
