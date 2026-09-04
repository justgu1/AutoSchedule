<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum GrantType: string
{
    case AuthorizationCode = 'authorization_code';
    case RefreshToken = 'refresh_token';
    case ClientCredentials = 'client_credentials';
}
