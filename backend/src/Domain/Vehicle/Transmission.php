<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

enum Transmission: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Automated = 'automated';
    case Cvt = 'cvt';
}
