<?php

declare(strict_types=1);

namespace App\Domain\Dealerships\DTO;

use App\Domain\Dealerships\Dealership;

/**
 * Perfil exposto por `GET /dealerships/{id}` pra quem não é dono/admin
 * (inclusive sem conta nenhuma) -- deliberadamente mais enxuto que
 * `DealershipProfile` (sem `id`/`owner_user_id`/`status`, que não interessam
 * nem devem vazar pro cliente final; `slug` no lugar do `id`, é o que monta
 * a URL amigável). `sellerName` é só o nome do vendedor -- nenhum outro dado
 * dele (telefone/e-mail do próprio `User`) é exposto aqui; contato é o
 * `phone`/`email` da concessionária mesma, não da pessoa.
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
            // Vazio até a Epic Veículo existir -- contrato já reservado aqui
            // pra o front não precisar mudar quando a listagem chegar.
            'vehicles' => [],
        ];
    }
}
