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

/** PATCH parcial: campo ausente mantém o gravado, como em `UpdateDealership`. */
final readonly class UpdateVehicle
{
    public function __construct(
        private VehicleFinder $finder,
        private DealershipFinder $dealerships,
        private VehicleRepository $vehicles,
        private VehicleAmenities $amenities,
        private AuditLogger $audit,
    ) {
    }

    /** @param ?list<string> $amenityIds */
    public function __invoke(?string $id, ValidatedInput $changes, ?array $amenityIds, ActorContext $context): VehicleProfile
    {
        $vehicle = $this->finder->findOrFail($id);
        $previousDealershipId = $vehicle->dealershipId;
        $price = $changes->decimalStringOrNull('price');

        $updated = $vehicle->withDetails(
            brand: $changes->stringOr('brand', $vehicle->brand),
            model: $changes->stringOr('model', $vehicle->model),
            version: $changes->stringOrNull('version') ?? $vehicle->version,
            manufactureYear: $changes->intOrNull('manufacture_year') ?? $vehicle->manufactureYear,
            modelYear: $changes->intOrNull('model_year') ?? $vehicle->modelYear,
            price: $price === null ? $vehicle->price : Money::fromDecimal($price),
            description: $changes->stringOrNull('description') ?? $vehicle->description,
            mileageKm: $changes->intOrNull('mileage_km') ?? $vehicle->mileageKm,
            transmission: $changes->enumOrNull('transmission', Transmission::class) ?? $vehicle->transmission,
            bodyType: $changes->enumOrNull('body_type', BodyType::class) ?? $vehicle->bodyType,
            fuelType: $changes->enumOrNull('fuel_type', FuelType::class) ?? $vehicle->fuelType,
            color: $changes->stringOrNull('color') ?? $vehicle->color,
            plateEndDigit: $changes->intOrNull('plate_end_digit') ?? $vehicle->plateEndDigit,
            acceptsTrade: $changes->boolOr('accepts_trade', $vehicle->acceptsTrade),
            ipvaPaid: $changes->boolOr('ipva_paid', $vehicle->ipvaPaid),
            licensed: $changes->boolOr('licensed', $vehicle->licensed),
        );

        if ($changes->has('dealership_id') && $changes->string('dealership_id') !== $previousDealershipId) {
            // O 404 do finder é a regra de quem pode mover: seller só enxerga as próprias, admin enxerga todas.
            $updated = $updated->movedTo($this->dealerships->findOrFail($changes->string('dealership_id'))->id);
        }

        $this->vehicles->update($updated);

        if ($amenityIds !== null) {
            $this->amenities->replace($updated->id, $amenityIds);
        }

        $this->audit->record($context->audits(AuditEvent::VehicleUpdated, $updated->id, ['fields' => $changes->fields()]));

        if ($updated->dealershipId !== $previousDealershipId) {
            $this->audit->record($context->audits(
                AuditEvent::VehicleDealershipReassigned,
                $updated->id,
                ['from' => $previousDealershipId, 'to' => $updated->dealershipId],
            ));
        }

        return VehicleProfile::fromVehicle($updated, amenities: $this->amenities->linksFor($updated->id));
    }
}
