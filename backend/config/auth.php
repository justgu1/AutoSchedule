<?php

declare(strict_types=1);

use App\Env;

return [
    'jwt' => [
        'issuer' => Env::string('JWT_ISSUER', 'autoschedule'),
        'audience' => Env::string('JWT_AUDIENCE', 'autoschedule-api'),
        'private_key_path' => Env::string('JWT_PRIVATE_KEY_PATH', dirname(__DIR__) . '/storage/keys/oauth-private.pem'),
        'public_key_path' => Env::string('JWT_PUBLIC_KEY_PATH', dirname(__DIR__) . '/storage/keys/oauth-public.pem'),
    ],

    'access_token_ttl' => Env::int('JWT_ACCESS_TOKEN_TTL', 900),
    'refresh_token_ttl' => Env::int('OAUTH_REFRESH_TOKEN_TTL', 1_209_600),
    'password_reset_ttl' => Env::int('PASSWORD_RESET_TTL', 3600),
];
