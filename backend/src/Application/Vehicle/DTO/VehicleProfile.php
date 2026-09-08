<?php

declare(strict_types=1);

namespace App\Application\Vehicle\DTO;

use App\Domain\Shared\Money;
use App\Domain\Vehicle\Vehicle;

final readonly class VehicleProfile
{
    /**
     * @param list<array{id: string, position: int, url: string}> $images
     * @param list<array{id: string, code: string, label: string}> $amenities
     */
    public function __construct(
        public string $id,
        public string $dealershipId,
        public string $brand,
        public string $model,
        public ?string $version,
        public ?int $manufactureYear,
        public ?int $modelYear,
        public Money $price,
        public ?string $description,
        public ?int $mileageKm,
        public ?string $transmission,
        public ?string $bodyType,
        public ?string $fuelType,
        public ?string $color,
        public ?int $plateEndDigit,
        public bool $acceptsTrade,
        public bool $ipvaPaid,
        public bool $licensed,
        public string $status,
        public ?string $photoUrl = null,
        public array $images = [],
        public array $amenities = [],
    ) {
    }

    /**
     * `$photoUrl`/`$images`/`$amenities` já resolvidos por quem chama -- o DTO não conhece `VehicleGallery`/`VehicleAmenities`.
     *
     * @param list<array{id: string, position: int, url: string}> $images
     * @param list<array{id: string, code: string, label: string}> $amenities
     */
    public static function fromVehicle(Vehicle $vehicle, ?string $photoUrl = null, array $images = [], array $amenities = []): self
    {
        return new self(
            id: $vehicle->id,
            dealershipId: $vehicle->dealershipId,
            brand: $vehicle->brand,
            model: $vehicle->model,
            version: $vehicle->version,
            manufactureYear: $vehicle->manufactureYear,
            modelYear: $vehicle->modelYear,
            price: $vehicle->price,
            description: $vehicle->description,
            mileageKm: $vehicle->mileageKm,
            transmission: $vehicle->transmission?->value,
            bodyType: $vehicle->bodyType?->value,
            fuelType: $vehicle->fuelType?->value,
            color: $vehicle->color,
            plateEndDigit: $vehicle->plateEndDigit,
            acceptsTrade: $vehicle->acceptsTrade,
            ipvaPaid: $vehicle->ipvaPaid,
            licensed: $vehicle->licensed,
            status: $vehicle->trash->status->value,
            photoUrl: $photoUrl,
            images: $images,
            amenities: $amenities,
        );
    }

    /**
     * `price` sai como string: número em JSON vira IEEE754 no cliente, que é o mesmo centavo
     * perdido que `numeric(12,2)` evita no banco.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dealership_id' => $this->dealershipId,
            'brand' => $this->brand,
            'model' => $this->model,
            'version' => $this->version,
            'manufacture_year' => $this->manufactureYear,
            'model_year' => $this->modelYear,
            'price' => $this->price->toDecimal(),
            'description' => $this->description,
            'mileage_km' => $this->mileageKm,
            'transmission' => $this->transmission,
            'body_type' => $this->bodyType,
            'fuel_type' => $this->fuelType,
            'color' => $this->color,
            'plate_end_digit' => $this->plateEndDigit,
            'accepts_trade' => $this->acceptsTrade,
            'ipva_paid' => $this->ipvaPaid,
            'licensed' => $this->licensed,
            'status' => $this->status,
            'photo_url' => $this->photoUrl,
            'images' => $this->images,
            'amenities' => $this->amenities,
        ];
    }
}
