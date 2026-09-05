<?php

declare(strict_types=1);

return [
    // Geral: headroom generoso sobre o pico esperado (prática recomendada pela
    // Cloudflare -- configurar 2-3x o tráfego normal, não o valor exato esperado).
    'general' => [
        'max_attempts' => (int) (getenv('RATE_LIMIT_GENERAL_MAX') ?: 1000),
        'window_seconds' => (int) (getenv('RATE_LIMIT_GENERAL_WINDOW') ?: 60),
    ],

    // Auth: 5/min é o número que a própria Cloudflare recomenda pra proteger
    // endpoint de login/signup contra brute-force sem incomodar uso legítimo.
    'auth' => [
        'max_attempts' => (int) (getenv('RATE_LIMIT_AUTH_MAX') ?: 5),
        'window_seconds' => (int) (getenv('RATE_LIMIT_AUTH_WINDOW') ?: 60),
    ],
];
