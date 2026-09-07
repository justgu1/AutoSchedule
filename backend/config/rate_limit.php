<?php

declare(strict_types=1);

use App\Env;

return [
    // Headroom de 2-3x sobre o pico esperado, como a Cloudflare recomenda -- não o valor exato.
    'general' => [
        'max_attempts' => Env::int('RATE_LIMIT_GENERAL_MAX', 1000),
        'window_seconds' => Env::int('RATE_LIMIT_GENERAL_WINDOW', 60),
    ],

    // 5/min é o que a Cloudflare recomenda contra brute-force sem incomodar uso legítimo.
    'auth' => [
        'max_attempts' => Env::int('RATE_LIMIT_AUTH_MAX', 5),
        'window_seconds' => Env::int('RATE_LIMIT_AUTH_WINDOW', 60),
    ],
];
