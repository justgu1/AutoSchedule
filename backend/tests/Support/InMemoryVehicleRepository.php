<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Shared\Money;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;

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

    public function search(VehicleFilters $filters, ?string $ownerUserId, int $limit, int $offset): array
    {
        $matching = array_values(array_filter(
            $this->vehicles,
            fn (Vehicle $v): bool => $this->isVisible($v) && $this->matches($v, $filters, $ownerUserId),
        ));

        return array_slice($matching, $offset, $limit);
    }

    public function countSearch(VehicleFilters $filters, ?string $ownerUserId): int
    {
        return count($this->search($filters, $ownerUserId, PHP_INT_MAX, 0));
    }

    public function availableFilters(?string $ownerUserId): array
    {
        $matching = $this->search(new VehicleFilters(), $ownerUserId, PHP_INT_MAX, 0);

        $brands = array_values(array_unique(array_map(static fn (Vehicle $v): string => $v->brand, $matching)));
        $models = array_values(array_unique(array_map(static fn (Vehicle $v): string => $v->model, $matching)));
        $years = array_values(array_unique(array_filter(array_map(static fn (Vehicle $v): ?int => $v->modelYear, $matching))));

        sort($brands);
        sort($models);
        rsort($years);

        return ['brands' => $brands, 'models' => $models, 'years' => $years, 'transmissions' => [], 'body_types' => [], 'fuel_types' => []];
    }

    public function searchPublic(VehicleFilters $filters, int $limit, int $offset): array
    {
        $matching = array_values(array_filter(
            $this->vehicles,
            fn (Vehicle $v): bool => $v->trash->status === TrashableStatus::Active && $this->matches($v, $filters, null),
        ));

        return array_slice($matching, $offset, $limit);
    }

    public function countSearchPublic(VehicleFilters $filters): int
    {
        return count($this->searchPublic($filters, PHP_INT_MAX, 0));
    }

    public function availableFiltersPublic(): array
    {
        $matching = $this->searchPublic(new VehicleFilters(), PHP_INT_MAX, 0);

        $brands = array_values(array_unique(array_map(static fn (Vehicle $v): string => $v->brand, $matching)));
        $models = array_values(array_unique(array_map(static fn (Vehicle $v): string => $v->model, $matching)));
        $years = array_values(array_unique(array_filter(array_map(static fn (Vehicle $v): ?int => $v->modelYear, $matching))));

        sort($brands);
        sort($models);
        rsort($years);

        return ['brands' => $brands, 'models' => $models, 'years' => $years, 'transmissions' => [], 'body_types' => [], 'fuel_types' => []];
    }

    /** Aproximação honesta do que o Postgres faz: casamento por substring no lugar do índice de texto. */
    private function matches(Vehicle $vehicle, VehicleFilters $filters, ?string $ownerUserId): bool
    {
        if ($ownerUserId !== null && $this->ownerOf($vehicle) !== $ownerUserId) {
            return false;
        }

        $haystack = mb_strtolower(implode(' ', [$vehicle->brand, $vehicle->model, $vehicle->version ?? '', $vehicle->description ?? '']));

        foreach ([$filters->term, $filters->brand, $filters->model] as $needle) {
            if ($needle !== null && !str_contains($haystack, mb_strtolower($needle))) {
                return false;
            }
        }

        return $this->withinRanges($vehicle, $filters);
    }

    private function withinRanges(Vehicle $vehicle, VehicleFilters $filters): bool
    {
        return ($filters->yearMin === null || ($vehicle->modelYear ?? 0) >= $filters->yearMin)
            && ($filters->yearMax === null || ($vehicle->modelYear ?? PHP_INT_MAX) <= $filters->yearMax)
            && (!$filters->priceMin instanceof Money || $vehicle->price->cents >= $filters->priceMin->cents)
            && (!$filters->priceMax instanceof Money || $vehicle->price->cents <= $filters->priceMax->cents)
            && ($filters->dealershipId === null || $vehicle->dealershipId === $filters->dealershipId);
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
            manufactureYear: $vehicle->manufactureYear,
            modelYear: $vehicle->modelYear,
            price: $vehicle->price,
            description: $vehicle->description,
            mileageKm: $vehicle->mileageKm,
            transmission: $vehicle->transmission,
            bodyType: $vehicle->bodyType,
            fuelType: $vehicle->fuelType,
            color: $vehicle->color,
            plateEndDigit: $vehicle->plateEndDigit,
            acceptsTrade: $vehicle->acceptsTrade,
            ipvaPaid: $vehicle->ipvaPaid,
            licensed: $vehicle->licensed,
            trash: $trash,
            trashedByDealershipTrash: $byDealershipTrash,
            createdAt: $vehicle->createdAt,
            updatedAt: $vehicle->updatedAt,
        );
    }
}
