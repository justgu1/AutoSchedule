<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Vehicle\DTO\PublicVehicleProfile;
use App\Application\Vehicle\DTO\VehicleProfile;

/**
 * Uma leitura, dois perfis: dono e admin recebem o completo, todo o resto recebe o público.
 * O RLS reforça isso na própria query (`vehicles_public_select`), então nem depende deste ramo estar certo.
 */
final readonly class ViewVehicle
{
    public function __construct(
        private VehicleFinder $finder,
        private VehicleGallery $gallery,
        private VehicleAmenities $amenities,
        private DealershipFinder $dealerships,
    ) {
    }

    public function __invoke(?string $id, ActorContext $context): VehicleProfile|PublicVehicleProfile
    {
        $vehicle = $this->finder->findOrFail($id);
        $images = $this->gallery->forVehicle($vehicle->id);
        $amenities = $this->amenities->linksFor($vehicle->id);
        $dealership = $this->dealerships->findOrFail($vehicle->dealershipId);

        if ($context->isAdmin() || ($context->actorId !== null && $context->actorId === $dealership->ownerUserId)) {
            return VehicleProfile::fromVehicle($vehicle, $images[0]['url'] ?? null, $images, $amenities);
        }

        return PublicVehicleProfile::fromVehicle($vehicle, $images, $amenities, $dealership);
    }
}
