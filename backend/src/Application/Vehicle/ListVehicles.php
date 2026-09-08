<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Shared\ActorContext;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;

/** Admin vê todos; seller só os das próprias concessionárias. */
final readonly class ListVehicles
{
    public function __construct(private VehicleRepository $vehicles)
    {
    }

    /** @return array{items: list<VehicleProfile>, total: int} */
    public function __invoke(ActorContext $context, int $limit, int $offset): array
    {
        if ($context->isAdmin()) {
            return [
                'items' => $this->toProfiles($this->vehicles->findPage($limit, $offset)),
                'total' => $this->vehicles->count(),
            ];
        }

        $ownerId = (string) $context->actorId;

        return [
            'items' => $this->toProfiles($this->vehicles->findByOwner($ownerId, $limit, $offset)),
            'total' => $this->vehicles->countByOwner($ownerId),
        ];
    }

    /**
     * @param list<Vehicle> $vehicles
     * @return list<VehicleProfile>
     */
    private function toProfiles(array $vehicles): array
    {
        return array_map(VehicleProfile::fromVehicle(...), $vehicles);
    }
}
