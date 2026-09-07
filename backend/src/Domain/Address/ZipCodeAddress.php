<?php

declare(strict_types=1);

namespace App\Domain\Address;

/** Endereço resolvido a partir de um CEP (ViaCEP hoje) -- sem lat/long, isso é sobre autopreencher formulário, não geocoding. */
final readonly class ZipCodeAddress
{
    public function __construct(
        public string $street,
        public string $neighborhood,
        public string $city,
        public string $state,
    ) {
    }
}
