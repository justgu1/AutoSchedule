<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

enum BodyType: string
{
    case Hatch = 'hatch';
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Pickup = 'pickup';
    case Coupe = 'coupe';
    case Convertible = 'convertible';
    case Minivan = 'minivan';
    case Wagon = 'wagon';
}
