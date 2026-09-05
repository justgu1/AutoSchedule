<?php

declare(strict_types=1);

return [
    'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('REDIS_PORT') ?: 6379),
    'prefix' => getenv('REDIS_PREFIX') ?: 'autoschedule',
    // Vazio por padrão -- Redis local (dev) não tem ACL/senha nenhuma. Um Redis
    // real com ACL (produção) troca isso só via env, sem mudar código.
    'username' => getenv('REDIS_USERNAME') ?: null,
    'password' => getenv('REDIS_PASSWORD') ?: null,
];
