<?php

declare(strict_types=1);

namespace App\Domain\Dealership\Ports;

use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Ports\TrashableRepository;

interface DealershipRepository extends TrashableRepository
{
    public function findById(string $id): ?Dealership;

    /** A página pública identifica pelo slug, nunca pelo id. */
    public function findBySlug(string $slug): ?Dealership;

    public function insert(Dealership $dealership): void;

    public function update(Dealership $dealership): void;

    /** @return list<Dealership> */
    public function findByOwner(string $ownerUserId, int $limit, int $offset): array;

    public function countByOwner(string $ownerUserId): int;

    /** @return list<Dealership> */
    public function findPage(int $limit, int $offset): array;

    public function count(): int;

    public function trash(string $id): void;

    public function restore(string $id): void;

    /** Cascata: manda pra lixeira toda concessionária ativa do dono desativado. */
    public function trashAllOwnedBy(string $ownerUserId): void;

    /** Cascata inversa: restaura só as que foram trashed por causa da desativação do dono (deixa quieto o que ele trashou manualmente). */
    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void;
}
