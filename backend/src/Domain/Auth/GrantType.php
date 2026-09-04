<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum GrantType: string
{
    case Password = 'password';
    case RefreshToken = 'refresh_token';
    case ClientCredentials = 'client_credentials';
}
