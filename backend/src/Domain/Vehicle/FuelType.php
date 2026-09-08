<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

enum FuelType: string
{
    case Flex = 'flex';
    case Gasoline = 'gasoline';
    case Ethanol = 'ethanol';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hybrid = 'hybrid';
}
