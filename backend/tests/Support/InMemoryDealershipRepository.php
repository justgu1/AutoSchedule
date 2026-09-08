<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;

/**
 * A lixeira e a cascata são SQL de massa no adapter real, então aqui elas são reimplementadas
 * -- é o que permite testar a composição de casos de uso sem subir Postgres.
 */
final class InMemoryDealershipRepository implements DealershipRepository
{
    /** @param array<string, Dealership> $dealerships */
    public function __construct(private array $dealerships = [])
    {
    }

    public function findById(string $id): ?Dealership
    {
        return $this->dealerships[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Dealership
    {
        foreach ($this->dealerships as $dealership) {
            if ($dealership->slug === $slug) {
                return $dealership;
            }
        }

        return null;
    }

    public function insert(Dealership $dealership): void
    {
        $this->dealerships[$dealership->id] = $dealership;
    }

    public function update(Dealership $dealership): void
    {
        $this->dealerships[$dealership->id] = $dealership;
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        $owned = array_filter(
            $this->dealerships,
            static fn (Dealership $d): bool => $d->ownerUserId === $ownerUserId && $d->trash->status !== TrashableStatus::Deleted,
        );

        return array_values(array_slice($owned, $offset, $limit));
    }

    public function countByOwner(string $ownerUserId): int
    {
        return count($this->findByOwner($ownerUserId, PHP_INT_MAX, 0));
    }

    public function findPage(int $limit, int $offset): array
    {
        $active = array_filter($this->dealerships, static fn (Dealership $d): bool => $d->trash->status !== TrashableStatus::Deleted);

        return array_values(array_slice($active, $offset, $limit));
    }

    public function count(): int
    {
        return count($this->findPage(PHP_INT_MAX, 0));
    }

    public function trash(string $id): void
    {
        $dealership = $this->findById($id);

        if ($dealership instanceof Dealership) {
            $this->dealerships[$id] = $this->withTrash($dealership, $dealership->trash->trashed(), false);
        }
    }

    public function restore(string $id): void
    {
        $dealership = $this->findById($id);

        if ($dealership instanceof Dealership) {
            $this->dealerships[$id] = $this->withTrash($dealership, $dealership->trash->restored(), false);
        }
    }

    public function findTrashed(): array
    {
        return array_values(array_filter(
            $this->dealerships,
            static fn (Dealership $d): bool => $d->trash->allowsRestore(),
        ));
    }

    public function purge(Trashable $entity): void
    {
        if ($entity instanceof Dealership) {
            $this->update($entity);
        }
    }

    public function trashAllOwnedBy(string $ownerUserId): void
    {
        foreach ($this->dealerships as $id => $dealership) {
            if ($dealership->ownerUserId === $ownerUserId && $dealership->trash->isActive()) {
                $this->dealerships[$id] = $this->withTrash($dealership, $dealership->trash->trashed(), true);
            }
        }
    }

    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void
    {
        foreach ($this->dealerships as $id => $dealership) {
            if ($dealership->ownerUserId === $ownerUserId && $dealership->trashedByOwnerDeactivation && $dealership->trash->isTrashed()) {
                $this->dealerships[$id] = $this->withTrash($dealership, $dealership->trash->restored(), false);
            }
        }
    }

    private function withTrash(Dealership $dealership, TrashState $trash, bool $byOwnerDeactivation): Dealership
    {
        return new Dealership(
            id: $dealership->id,
            ownerUserId: $dealership->ownerUserId,
            name: $dealership->name,
            slug: $dealership->slug,
            address: $dealership->address,
            phone: $dealership->phone,
            email: $dealership->email,
            photoFileId: $dealership->photoFileId,
            trash: $trash,
            trashedByOwnerDeactivation: $byOwnerDeactivation,
            createdAt: $dealership->createdAt,
            updatedAt: $dealership->updatedAt,
        );
    }
}
