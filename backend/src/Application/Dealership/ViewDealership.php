<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Dealership\DTO\DealershipProfile;
use App\Application\Dealership\DTO\PublicDealershipProfile;
use App\Application\Shared\ActorContext;
use App\Application\Vehicle\DTO\PublicVehicleSummary;
use App\Application\Vehicle\VehicleGallery;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;

/**
 * Uma leitura, dois perfis: dono e admin recebem o completo, todo o resto recebe o público.
 * O RLS reforça isso na própria query, então nem depende deste ramo estar certo.
 */
final readonly class ViewDealership
{
    /** Vitrine é preview, não paginação -- uma segunda página de veículo é o catálogo, com seu próprio filtro. */
    private const int SHOWCASE_LIMIT = 12;

    public function __construct(
        private DealershipFinder $finder,
        private DealershipPhotos $photos,
        private UserRepository $users,
        private VehicleRepository $vehicles,
        private VehicleGallery $gallery,
    ) {
    }

    public function __invoke(?string $identifier, ActorContext $context): DealershipProfile|PublicDealershipProfile
    {
        $dealership = $this->finder->findOrFail($identifier);

        if ($context->isAdmin() || ($context->actorId !== null && $context->actorId === $dealership->ownerUserId)) {
            return DealershipProfile::fromDealership($dealership, $this->photos->urlFor($dealership));
        }

        $seller = $this->users->findById($dealership->ownerUserId);
        $filters = new VehicleFilters(dealershipId: $dealership->id);
        $found = $this->vehicles->searchPublic($filters, self::SHOWCASE_LIMIT, 0);
        $covers = $this->gallery->coversFor(array_map(static fn (Vehicle $v): string => $v->id, $found));

        return PublicDealershipProfile::fromDealership(
            $dealership,
            $this->photos->urlFor($dealership),
            $seller instanceof User ? $seller->name : null,
            array_map(
                static fn (Vehicle $v): PublicVehicleSummary => PublicVehicleSummary::fromVehicle($v, $covers[$v->id] ?? null),
                $found,
            ),
            $this->vehicles->countSearchPublic($filters),
        );
    }
}
