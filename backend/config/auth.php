<?php

declare(strict_types=1);

return [
    'jwt' => [
        'issuer' => getenv('JWT_ISSUER') ?: 'autoschedule',
        'audience' => getenv('JWT_AUDIENCE') ?: 'autoschedule-api',
        'private_key_path' => getenv('JWT_PRIVATE_KEY_PATH') ?: dirname(__DIR__) . '/storage/keys/oauth-private.pem',
        'public_key_path' => getenv('JWT_PUBLIC_KEY_PATH') ?: dirname(__DIR__) . '/storage/keys/oauth-public.pem',
    ],

    'access_token_ttl' => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: 900),
    'authorization_code_ttl' => (int) (getenv('OAUTH_AUTHORIZATION_CODE_TTL') ?: 60),
    'refresh_token_ttl' => (int) (getenv('OAUTH_REFRESH_TOKEN_TTL') ?: 1_209_600),
];
