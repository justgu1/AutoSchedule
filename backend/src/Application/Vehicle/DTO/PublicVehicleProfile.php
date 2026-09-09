<?php

declare(strict_types=1);

namespace App\Application\Vehicle\DTO;

use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Money;
use App\Domain\Vehicle\Vehicle;

/** Sem `dealership_id`/`status` -- quem visita não gerencia nada. Concessionária aninhada e enxuta. */
final readonly class PublicVehicleProfile
{
    /**
     * @param list<array{id: string, position: int, url: string}> $images
     * @param list<array{id: string, code: string, label: string}> $amenities
     */
    public function __construct(
        public string $id,
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
        public array $images,
        public array $amenities,
        public string $dealershipSlug,
        public string $dealershipName,
        public string $dealershipCity,
        public string $dealershipState,
    ) {
    }

    /**
     * @param list<array{id: string, position: int, url: string}> $images
     * @param list<array{id: string, code: string, label: string}> $amenities
     */
    public static function fromVehicle(Vehicle $vehicle, array $images, array $amenities, Dealership $dealership): self
    {
        return new self(
            id: $vehicle->id,
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
            images: $images,
            amenities: $amenities,
            dealershipSlug: $dealership->slug,
            dealershipName: $dealership->name,
            dealershipCity: $dealership->address->city,
            dealershipState: $dealership->address->state->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
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
            'images' => $this->images,
            'amenities' => $this->amenities,
            'dealership' => [
                'slug' => $this->dealershipSlug,
                'name' => $this->dealershipName,
                'city' => $this->dealershipCity,
                'state' => $this->dealershipState,
            ],
        ];
    }
}
