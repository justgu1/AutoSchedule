<?php

declare(strict_types=1);

namespace App\Application\Dealership\DTO;

use App\Application\Shared\AddressFields;
use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;
use App\Domain\Shared\Email;

/**
 * Deliberadamente mais enxuto que `DealershipProfile`: sem `id`/`owner_user_id`/`status`, e `slug` no lugar do id.
 * Do vendedor sai só o nome -- contato exposto é o da concessionária, nunca o da pessoa.
 */
final readonly class PublicDealershipProfile
{
    public function __construct(
        public string $slug,
        public string $name,
        public Address $address,
        public ?string $phone,
        public ?Email $email,
        public ?string $photoUrl,
        public ?string $sellerName,
    ) {
    }

    /** `$photoUrl`/`$sellerName` já resolvidos por quem chama -- o DTO não conhece storage nem `UserRepository`. */
    public static function fromDealership(Dealership $dealership, ?string $photoUrl, ?string $sellerName): self
    {
        return new self(
            slug: $dealership->slug,
            name: $dealership->name,
            address: $dealership->address,
            phone: $dealership->phone,
            email: $dealership->email,
            photoUrl: $photoUrl,
            sellerName: $sellerName,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            ...AddressFields::toArray($this->address),
            'phone' => $this->phone,
            'email' => $this->email?->value,
            'photo_url' => $this->photoUrl,
            'seller_name' => $this->sellerName,
            // Reservado: o front já lê a chave, então a Epic Veículo não muda o contrato.
            'vehicles' => [],
        ];
    }
}
