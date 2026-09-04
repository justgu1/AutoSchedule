<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum ClientType: string
{
    case Public = 'public';
    case Confidential = 'confidential';
}
