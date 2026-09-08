<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/** Os sete campos nunca andam sozinhos, e antes disto eram enumerados nominalmente em uns vinte pontos. */
final readonly class Address
{
    public function __construct(
        public string $zipCode,
        public string $street,
        public string $number,
        public ?string $complement,
        public string $neighborhood,
        public string $city,
        public Uf $state,
    ) {
    }

    /** A anonimização derruba o que localiza a porta e mantém o que só serve agregado. */
    #[\NoDiscard]
    public function withoutStreetLevelDetail(): self
    {
        return clone($this, ['street' => '', 'number' => '', 'complement' => null]);
    }
}
