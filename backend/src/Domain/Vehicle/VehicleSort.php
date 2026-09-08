<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/** Ordenação explícita do catálogo -- quando ausente, a listagem cai na relevância de busca + mais recente. */
enum VehicleSort: string
{
    case PriceDesc = 'price_desc';
    case PriceAsc = 'price_asc';
    case YearDesc = 'year_desc';
    case CreatedDesc = 'created_desc';
    case CreatedAsc = 'created_asc';
}
