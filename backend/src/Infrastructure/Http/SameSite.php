<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

enum SameSite: string
{
    case Strict = 'Strict';
    case Lax = 'Lax';
    case None = 'None';
}
