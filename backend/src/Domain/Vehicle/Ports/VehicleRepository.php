<?php

declare(strict_types=1);

namespace App\Domain\Vehicle\Ports;

use App\Domain\Shared\Ports\TrashableRepository;
use App\Domain\Vehicle\Vehicle;

interface VehicleRepository extends TrashableRepository
{
    public function findById(string $id): ?Vehicle;

    public function insert(Vehicle $vehicle): void;

    public function update(Vehicle $vehicle): void;

    /** @return list<Vehicle> */
    public function findByOwner(string $ownerUserId, int $limit, int $offset): array;

    public function countByOwner(string $ownerUserId): int;

    /** @return list<Vehicle> */
    public function findByDealership(string $dealershipId, int $limit, int $offset): array;

    public function countByDealership(string $dealershipId): int;

    /** @return list<Vehicle> */
    public function findPage(int $limit, int $offset): array;

    public function count(): int;

    public function trash(string $id): void;

    public function restore(string $id): void;

    public function trashAllInDealership(string $dealershipId): void;

    public function restoreAutoTrashedInDealership(string $dealershipId): void;

    /**
     * A desativação de conta arrasta concessionária e veículo de uma vez -- `TrashAccount` nunca passa pelo
     * caso de uso de concessionária, então a cascata de segundo nível precisa existir aqui.
     */
    public function trashAllOwnedByUser(string $ownerUserId): void;

    public function restoreAutoTrashedOwnedByUser(string $ownerUserId): void;
}
