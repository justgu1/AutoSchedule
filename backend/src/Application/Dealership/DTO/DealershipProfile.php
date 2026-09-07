<?php

declare(strict_types=1);

namespace App\Application\Dealership\DTO;

use App\Domain\Dealership\Dealership;

final readonly class DealershipProfile
{
    public function __construct(
        public string $id,
        public string $ownerUserId,
        public string $name,
        public string $slug,
        public string $zipCode,
        public string $address,
        public string $number,
        public ?string $complement,
        public string $neighborhood,
        public string $city,
        public string $state,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $googlePlaceId,
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
            zipCode: $dealership->zipCode,
            address: $dealership->address,
            number: $dealership->number,
            complement: $dealership->complement,
            neighborhood: $dealership->neighborhood,
            city: $dealership->city,
            state: $dealership->state,
            latitude: $dealership->latitude,
            longitude: $dealership->longitude,
            googlePlaceId: $dealership->googlePlaceId,
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
            'zip_code' => $this->zipCode,
            'address' => $this->address,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'google_place_id' => $this->googlePlaceId,
            'phone' => $this->phone,
            'email' => $this->email,
            'photo_url' => $this->photoUrl,
            'status' => $this->status,
        ];
    }
}
