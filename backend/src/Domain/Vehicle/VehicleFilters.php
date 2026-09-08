<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Shared\Money;

/**
 * `brand` e `model` passam pelo índice de texto, não por igualdade -- é isso que faz o filtro
 * de marca alcançar o anúncio que só escreveu a marca na descrição.
 */
final readonly class VehicleFilters
{
    public function __construct(
        public ?string $term = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?int $yearMin = null,
        public ?int $yearMax = null,
        public ?Money $priceMin = null,
        public ?Money $priceMax = null,
        public ?string $dealershipId = null,
    ) {
    }
}
