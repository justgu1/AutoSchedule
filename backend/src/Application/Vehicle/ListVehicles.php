<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Shared\ActorContext;
use App\Application\Vehicle\DTO\PublicVehicleSummary;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;

/** Admin vê todo o estoque; seller só o das próprias concessionárias -- é o painel de gestão. */
final readonly class ListVehicles
{
    public function __construct(
        private VehicleRepository $vehicles,
        private VehicleGallery $gallery,
    ) {
    }

    /** @return array{items: list<VehicleProfile>, total: int} */
    public function __invoke(ActorContext $context, VehicleFilters $filters, int $limit, int $offset): array
    {
        $ownerUserId = $context->isAdmin() ? null : (string) $context->actorId;
        $found = $this->vehicles->search($filters, $ownerUserId, $limit, $offset);
        $covers = $this->coversFor($found);

        return [
            'items' => array_map(
                static fn (Vehicle $v): VehicleProfile => VehicleProfile::fromVehicle($v, $covers[$v->id] ?? null),
                $found,
            ),
            'total' => $this->vehicles->countSearch($filters, $ownerUserId),
        ];
    }

    /** @return array{brands: list<string>, models: list<string>, years: list<int>} */
    public function availableFilters(ActorContext $context): array
    {
        return $this->vehicles->availableFilters($context->isAdmin() ? null : (string) $context->actorId);
    }

    /**
     * O catálogo do site: todo mundo vê o mesmo, dono logado ou não, e só o que está
     * publicamente visível -- diferente de `__invoke()`, que é o painel de quem gerencia.
     *
     * @return array{items: list<PublicVehicleSummary>, total: int}
     */
    public function catalog(VehicleFilters $filters, int $limit, int $offset): array
    {
        $found = $this->vehicles->searchPublic($filters, $limit, $offset);
        $covers = $this->coversFor($found);

        return [
            'items' => array_map(
                static fn (Vehicle $v): PublicVehicleSummary => PublicVehicleSummary::fromVehicle($v, $covers[$v->id] ?? null),
                $found,
            ),
            'total' => $this->vehicles->countSearchPublic($filters),
        ];
    }

    /** @return array{brands: list<string>, models: list<string>, years: list<int>} */
    public function availableFiltersPublic(): array
    {
        return $this->vehicles->availableFiltersPublic();
    }

    /**
     * @param list<Vehicle> $vehicles
     * @return array<string, string>
     */
    private function coversFor(array $vehicles): array
    {
        return $this->gallery->coversFor(array_map(static fn (Vehicle $v): string => $v->id, $vehicles));
    }
}
