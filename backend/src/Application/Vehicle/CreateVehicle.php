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
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;

/** O 404 do `DealershipFinder` já é a checagem de dono -- concessionária alheia não é encontrável. */
final readonly class CreateVehicle
{
    public function __construct(
        private DealershipFinder $dealerships,
        private VehicleRepository $vehicles,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(ValidatedInput $data, ActorContext $context): VehicleProfile
    {
        $dealership = $this->dealerships->findOrFail($data->string('dealership_id'));

        $vehicle = Vehicle::register(
            dealershipId: $dealership->id,
            brand: $data->string('brand'),
            model: $data->string('model'),
            version: $data->stringOrNull('version'),
            year: $data->intOrNull('year'),
            price: Money::fromDecimal((string) $data->decimalStringOrNull('price')),
            description: $data->stringOrNull('description'),
        );

        $this->vehicles->insert($vehicle);
        $this->audit->record($context->audits(AuditEvent::VehicleCreated, $vehicle->id));

        return VehicleProfile::fromVehicle($vehicle);
    }
}
