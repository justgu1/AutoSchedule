<?php

declare(strict_types=1);

use App\Env;

return [
    'hsts_enabled' => Env::bool('SECURITY_HSTS_ENABLED', false),
    'cookie_secure' => Env::bool('COOKIE_SECURE', false),
];
