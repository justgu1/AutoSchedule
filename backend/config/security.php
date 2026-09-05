<?php

declare(strict_types=1);

return [
    'hsts_enabled' => filter_var(getenv('SECURITY_HSTS_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
    'cookie_secure' => filter_var(getenv('COOKIE_SECURE') ?: false, FILTER_VALIDATE_BOOL),
];
