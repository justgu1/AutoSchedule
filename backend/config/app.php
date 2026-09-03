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
];