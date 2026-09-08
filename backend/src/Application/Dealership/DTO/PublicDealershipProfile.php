<?php

declare(strict_types=1);

namespace App\Application\Dealership\DTO;

use App\Domain\Dealership\Dealership;

/**
 * Deliberadamente mais enxuto que `DealershipProfile`: sem `id`/`owner_user_id`/`status`, e `slug` no lugar do id.
 * Do vendedor sai só o nome -- contato exposto é o da concessionária, nunca o da pessoa.
 */
final readonly class PublicDealershipProfile
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $zipCode,
        public string $address,
        public string $number,
        public ?string $complement,
        public string $neighborhood,
        public string $city,
        public string $state,
        public ?string $phone,
        public ?string $email,
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
            zipCode: $dealership->zipCode,
            address: $dealership->address,
            number: $dealership->number,
            complement: $dealership->complement,
            neighborhood: $dealership->neighborhood,
            city: $dealership->city,
            state: $dealership->state,
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
            'zip_code' => $this->zipCode,
            'address' => $this->address,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'phone' => $this->phone,
            'email' => $this->email,
            'photo_url' => $this->photoUrl,
            'seller_name' => $this->sellerName,
            // Reservado: o front já lê a chave, então a Epic Veículo não muda o contrato.
            'vehicles' => [],
        ];
    }
}
