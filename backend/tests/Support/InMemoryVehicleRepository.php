<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;

/**
 * A lixeira e as duas cascatas são SQL de massa no adapter real, então aqui elas são reimplementadas
 * -- é o que permite testar a composição de casos de uso sem subir Postgres.
 */
final class InMemoryVehicleRepository implements VehicleRepository
{
    /** @var array<string, Vehicle> */
    private array $vehicles = [];

    /** @param array<string, string> $dealershipOwners concessionária => dono, pro que o adapter real resolve por JOIN */
    public function __construct(private array $dealershipOwners = [])
    {
    }

    public function findById(string $id): ?Vehicle
    {
        return $this->vehicles[$id] ?? null;
    }

    public function insert(Vehicle $vehicle): void
    {
        $this->vehicles[$vehicle->id] = $vehicle;
    }

    public function update(Vehicle $vehicle): void
    {
        $this->vehicles[$vehicle->id] = $vehicle;
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        $owned = array_filter($this->vehicles, fn (Vehicle $v): bool => $this->ownerOf($v) === $ownerUserId && $this->isVisible($v));

        return array_values(array_slice($owned, $offset, $limit));
    }

    public function countByOwner(string $ownerUserId): int
    {
        return count($this->findByOwner($ownerUserId, PHP_INT_MAX, 0));
    }

    public function findByDealership(string $dealershipId, int $limit, int $offset): array
    {
        $inDealership = array_filter(
            $this->vehicles,
            fn (Vehicle $v): bool => $v->dealershipId === $dealershipId && $this->isVisible($v),
        );

        return array_values(array_slice($inDealership, $offset, $limit));
    }

    public function countByDealership(string $dealershipId): int
    {
        return count($this->findByDealership($dealershipId, PHP_INT_MAX, 0));
    }

    public function findPage(int $limit, int $offset): array
    {
        return array_values(array_slice(array_filter($this->vehicles, $this->isVisible(...)), $offset, $limit));
    }

    public function count(): int
    {
        return count($this->findPage(PHP_INT_MAX, 0));
    }

    public function trash(string $id): void
    {
        $vehicle = $this->findById($id);

        if ($vehicle instanceof Vehicle) {
            $this->vehicles[$id] = $this->withTrash($vehicle, $vehicle->trash->trashed(), false);
        }
    }

    public function restore(string $id): void
    {
        $vehicle = $this->findById($id);

        if ($vehicle instanceof Vehicle) {
            $this->vehicles[$id] = $this->withTrash($vehicle, $vehicle->trash->restored(), false);
        }
    }

    public function findTrashed(): array
    {
        return array_values(array_filter($this->vehicles, static fn (Vehicle $v): bool => $v->trash->allowsRestore()));
    }

    public function purge(Trashable $entity): void
    {
        if ($entity instanceof Vehicle) {
            $this->update($entity);
        }
    }

    public function trashAllInDealership(string $dealershipId): void
    {
        $this->cascadeTrash(fn (Vehicle $v): bool => $v->dealershipId === $dealershipId);
    }

    public function restoreAutoTrashedInDealership(string $dealershipId): void
    {
        $this->cascadeRestore(fn (Vehicle $v): bool => $v->dealershipId === $dealershipId);
    }

    public function trashAllOwnedByUser(string $ownerUserId): void
    {
        $this->cascadeTrash(fn (Vehicle $v): bool => $this->ownerOf($v) === $ownerUserId);
    }

    public function restoreAutoTrashedOwnedByUser(string $ownerUserId): void
    {
        $this->cascadeRestore(fn (Vehicle $v): bool => $this->ownerOf($v) === $ownerUserId);
    }

    /** @param \Closure(Vehicle): bool $matches */
    private function cascadeTrash(\Closure $matches): void
    {
        foreach ($this->vehicles as $id => $vehicle) {
            if ($matches($vehicle) && $vehicle->trash->isActive()) {
                $this->vehicles[$id] = $this->withTrash($vehicle, $vehicle->trash->trashed(), true);
            }
        }
    }

    /** @param \Closure(Vehicle): bool $matches */
    private function cascadeRestore(\Closure $matches): void
    {
        foreach ($this->vehicles as $id => $vehicle) {
            if ($matches($vehicle) && $vehicle->trashedByDealershipTrash && $vehicle->trash->isTrashed()) {
                $this->vehicles[$id] = $this->withTrash($vehicle, $vehicle->trash->restored(), false);
            }
        }
    }

    private function isVisible(Vehicle $vehicle): bool
    {
        return $vehicle->trash->status !== TrashableStatus::Deleted;
    }

    private function ownerOf(Vehicle $vehicle): ?string
    {
        return $this->dealershipOwners[$vehicle->dealershipId] ?? null;
    }

    private function withTrash(Vehicle $vehicle, TrashState $trash, bool $byDealershipTrash): Vehicle
    {
        return new Vehicle(
            id: $vehicle->id,
            dealershipId: $vehicle->dealershipId,
            brand: $vehicle->brand,
            model: $vehicle->model,
            version: $vehicle->version,
            year: $vehicle->year,
            price: $vehicle->price,
            description: $vehicle->description,
            trash: $trash,
            trashedByDealershipTrash: $byDealershipTrash,
            createdAt: $vehicle->createdAt,
            updatedAt: $vehicle->updatedAt,
        );
    }
}
