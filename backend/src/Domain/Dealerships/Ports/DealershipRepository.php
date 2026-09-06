<?php

declare(strict_types=1);

namespace App\Domain\Dealerships\Ports;

use App\Domain\Dealerships\Dealership;
use App\Domain\Dealerships\DealershipImage;

interface DealershipRepository
{
    public function findById(string $id): ?Dealership;

    public function insert(Dealership $dealership): void;

    public function update(Dealership $dealership): void;

    /** @return list<Dealership> */
    public function findByOwner(string $ownerUserId, int $limit, int $offset): array;

    public function countByOwner(string $ownerUserId): int;

    /** @return list<Dealership> */
    public function findPage(int $limit, int $offset): array;

    public function count(): int;

    /** Move pra lixeira -- `byOwnerDeactivation` marca se foi cascata da desativação do dono (restore seletivo depois). */
    public function trash(string $id, bool $byOwnerDeactivation): void;

    public function restore(string $id): void;

    /** @return list<Dealership> */
    public function findPurgeEligible(int $graceDays, \DateTimeImmutable $now): array;

    /** Cascata: manda pra lixeira toda concessionária ativa do dono desativado. */
    public function trashAllOwnedBy(string $ownerUserId): void;

    /** Cascata inversa: restaura só as que foram trashed por causa da desativação do dono (deixa quieto o que ele trashou manualmente). */
    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void;

    public function insertImage(DealershipImage $image): void;

    public function deleteImage(string $imageId): void;

    public function findImageById(string $imageId): ?DealershipImage;

    /** @return list<DealershipImage> ordenado por position. */
    public function findImagesByDealership(string $dealershipId): array;

    /** Próxima posição livre da galeria -- 0 se ainda não tem nenhuma imagem. */
    public function nextImagePosition(string $dealershipId): int;
}
