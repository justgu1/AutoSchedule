<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Shared\ActorContext;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\VehicleFilters;

/** Admin vê todo o estoque; seller só o das próprias concessionárias. */
final readonly class ListVehicles
{
    public function __construct(private VehicleRepository $vehicles)
    {
    }

    /** @return array{items: list<VehicleProfile>, total: int} */
    public function __invoke(ActorContext $context, VehicleFilters $filters, int $limit, int $offset): array
    {
        $ownerUserId = $context->isAdmin() ? null : (string) $context->actorId;

        return [
            'items' => array_map(VehicleProfile::fromVehicle(...), $this->vehicles->search($filters, $ownerUserId, $limit, $offset)),
            'total' => $this->vehicles->countSearch($filters, $ownerUserId),
        ];
    }

    /** @return array{brands: list<string>, models: list<string>, years: list<int>} */
    public function availableFilters(ActorContext $context): array
    {
        return $this->vehicles->availableFilters($context->isAdmin() ? null : (string) $context->actorId);
    }
}
