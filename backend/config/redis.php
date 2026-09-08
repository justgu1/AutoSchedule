<?php

declare(strict_types=1);

use App\Env;

return [
    'host' => Env::string('REDIS_HOST', '127.0.0.1'),
    'port' => Env::int('REDIS_PORT', 6379),
    'prefix' => Env::string('REDIS_PREFIX', 'autoschedule'),
    // Nulo em dev: Redis local não tem ACL, e um cluster com ACL só precisa preencher a env.
    'username' => Env::stringOrNull('REDIS_USERNAME'),
    'password' => Env::stringOrNull('REDIS_PASSWORD'),
];
