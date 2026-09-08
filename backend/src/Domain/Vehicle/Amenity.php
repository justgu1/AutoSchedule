<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/** Item do catálogo global de equipamentos/itens de veículo (seedado, somente leitura via API). */
final readonly class Amenity
{
    public function __construct(
        public string $id,
        public string $code,
        public string $label,
        public int $position,
    ) {
    }
}
