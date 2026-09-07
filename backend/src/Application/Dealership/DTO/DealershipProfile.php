<?php

declare(strict_types=1);

namespace App\Application\Dealership\DTO;

use App\Application\Shared\AddressFields;
use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;

final readonly class DealershipProfile
{
    public function __construct(
        public string $id,
        public string $ownerUserId,
        public string $name,
        public string $slug,
        public Address $address,
        public ?string $phone,
        public ?string $email,
        public ?string $photoUrl,
        public string $status,
    ) {
    }

    /** `$photoUrl` já resolvido por quem chama (`StorageProvider::url()`) -- o DTO não conhece storage. */
    public static function fromDealership(Dealership $dealership, ?string $photoUrl): self
    {
        return new self(
            id: $dealership->id,
            ownerUserId: $dealership->ownerUserId,
            name: $dealership->name,
            slug: $dealership->slug,
            address: $dealership->address,
            phone: $dealership->phone,
            email: $dealership->email,
            photoUrl: $photoUrl,
            status: $dealership->trash->status->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner_user_id' => $this->ownerUserId,
            'name' => $this->name,
            'slug' => $this->slug,
            ...AddressFields::toArray($this->address),
            'phone' => $this->phone,
            'email' => $this->email,
            'photo_url' => $this->photoUrl,
            'status' => $this->status,
        ];
    }
}
