<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Shared\Money;
use App\Domain\Vehicle\BodyType;
use App\Domain\Vehicle\FuelType;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Transmission;
use App\Domain\Vehicle\Vehicle;

/** O 404 do `DealershipFinder` já é a checagem de dono -- concessionária alheia não é encontrável. */
final readonly class CreateVehicle
{
    public function __construct(
        private DealershipFinder $dealerships,
        private VehicleRepository $vehicles,
        private VehicleAmenities $amenities,
        private AuditLogger $audit,
    ) {
    }

    /** @param ?list<string> $amenityIds */
    public function __invoke(ValidatedInput $data, ?array $amenityIds, ActorContext $context): VehicleProfile
    {
        $dealership = $this->dealerships->findOrFail($data->string('dealership_id'));

        $vehicle = Vehicle::register(
            dealershipId: $dealership->id,
            brand: $data->string('brand'),
            model: $data->string('model'),
            version: $data->stringOrNull('version'),
            manufactureYear: $data->intOrNull('manufacture_year'),
            modelYear: $data->intOrNull('model_year'),
            price: Money::fromDecimal((string) $data->decimalStringOrNull('price')),
            description: $data->stringOrNull('description'),
            mileageKm: $data->intOrNull('mileage_km'),
            transmission: $data->enumOrNull('transmission', Transmission::class),
            bodyType: $data->enumOrNull('body_type', BodyType::class),
            fuelType: $data->enumOrNull('fuel_type', FuelType::class),
            color: $data->stringOrNull('color'),
            plateEndDigit: $data->intOrNull('plate_end_digit'),
            acceptsTrade: $data->boolOr('accepts_trade', false),
            ipvaPaid: $data->boolOr('ipva_paid', false),
            licensed: $data->boolOr('licensed', false),
        );

        $this->vehicles->insert($vehicle);

        if ($amenityIds !== null) {
            $this->amenities->replace($vehicle->id, $amenityIds);
        }

        $this->audit->record($context->audits(AuditEvent::VehicleCreated, $vehicle->id));

        return VehicleProfile::fromVehicle($vehicle, amenities: $this->amenities->linksFor($vehicle->id));
    }
}
