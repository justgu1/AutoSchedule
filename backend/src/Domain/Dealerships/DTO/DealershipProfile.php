<?php

declare(strict_types=1);

namespace App\Domain\Dealerships\DTO;

use App\Domain\Dealerships\Dealership;

final readonly class DealershipProfile
{
    public function __construct(
        public string $id,
        public string $ownerUserId,
        public string $name,
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
        public string $status,
    ) {
    }

    public static function fromDealership(Dealership $dealership): self
    {
        return new self(
            id: $dealership->id,
            ownerUserId: $dealership->ownerUserId,
            name: $dealership->name,
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
            status: $dealership->status->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner_user_id' => $this->ownerUserId,
            'name' => $this->name,
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
            'status' => $this->status,
        ];
    }
}
