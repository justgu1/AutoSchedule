<?php

declare(strict_types=1);

namespace App\Domain\Dealerships;

use App\Domain\Shared\TrashableStatus;
use App\Domain\Support\Uuid;

final readonly class Dealership
{
    public function __construct(
        public string $id,
        public string $ownerUserId,
        public string $name,
        public string $zipCode,
        public string $address,
        public string $number,
        public ?string $complement,
        public string $neighborhood,
        public string $city,
        public string $state,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $googlePlaceId,
        public ?string $phone,
        public ?string $photoFileId,
        public TrashableStatus $status,
        public bool $trashedByOwnerDeactivation,
        public ?\DateTimeImmutable $trashedAt,
        public ?\DateTimeImmutable $anonymizedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function register(
        string $ownerUserId,
        string $name,
        string $zipCode,
        string $address,
        string $number,
        ?string $complement,
        string $neighborhood,
        string $city,
        string $state,
        ?string $phone,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $googlePlaceId = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            ownerUserId: $ownerUserId,
            name: $name,
            zipCode: $zipCode,
            address: $address,
            number: $number,
            complement: $complement,
            neighborhood: $neighborhood,
            city: $city,
            state: $state,
            latitude: $latitude,
            longitude: $longitude,
            googlePlaceId: $googlePlaceId,
            phone: $phone,
            photoFileId: null,
            status: TrashableStatus::Active,
            trashedByOwnerDeactivation: false,
            trashedAt: null,
            anonymizedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /** Devolve uma cópia com os dados de perfil atualizados -- usado por `PATCH /dealerships/{id}`. */
    public function withProfile(
        string $name,
        string $zipCode,
        string $address,
        string $number,
        ?string $complement,
        string $neighborhood,
        string $city,
        string $state,
        ?string $phone,
        ?float $latitude,
        ?float $longitude,
        ?string $googlePlaceId,
    ): self {
        return new self(
            id: $this->id,
            ownerUserId: $this->ownerUserId,
            name: $name,
            zipCode: $zipCode,
            address: $address,
            number: $number,
            complement: $complement,
            neighborhood: $neighborhood,
            city: $city,
            state: $state,
            latitude: $latitude,
            longitude: $longitude,
            googlePlaceId: $googlePlaceId,
            phone: $phone,
            photoFileId: $this->photoFileId,
            status: $this->status,
            trashedByOwnerDeactivation: $this->trashedByOwnerDeactivation,
            trashedAt: $this->trashedAt,
            anonymizedAt: $this->anonymizedAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    /** Admin reassociando a concessionária a outro seller. */
    public function withOwner(string $ownerUserId): self
    {
        return new self(
            id: $this->id,
            ownerUserId: $ownerUserId,
            name: $this->name,
            zipCode: $this->zipCode,
            address: $this->address,
            number: $this->number,
            complement: $this->complement,
            neighborhood: $this->neighborhood,
            city: $this->city,
            state: $this->state,
            latitude: $this->latitude,
            longitude: $this->longitude,
            googlePlaceId: $this->googlePlaceId,
            phone: $this->phone,
            photoFileId: $this->photoFileId,
            status: $this->status,
            trashedByOwnerDeactivation: $this->trashedByOwnerDeactivation,
            trashedAt: $this->trashedAt,
            anonymizedAt: $this->anonymizedAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    /** Só uma foto por concessionária -- setar substitui a anterior (quem chama cuida de remover o arquivo velho do storage). */
    public function withPhoto(?string $photoFileId): self
    {
        return new self(
            id: $this->id,
            ownerUserId: $this->ownerUserId,
            name: $this->name,
            zipCode: $this->zipCode,
            address: $this->address,
            number: $this->number,
            complement: $this->complement,
            neighborhood: $this->neighborhood,
            city: $this->city,
            state: $this->state,
            latitude: $this->latitude,
            longitude: $this->longitude,
            googlePlaceId: $this->googlePlaceId,
            phone: $this->phone,
            photoFileId: $photoFileId,
            status: $this->status,
            trashedByOwnerDeactivation: $this->trashedByOwnerDeactivation,
            trashedAt: $this->trashedAt,
            anonymizedAt: $this->anonymizedAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    /** Só dá pra restaurar da lixeira antes da anonimização definitiva ter rodado. */
    public function isEligibleForRestore(): bool
    {
        return $this->status === TrashableStatus::Trashed && !$this->anonymizedAt instanceof \DateTimeImmutable;
    }

    /** Passou da janela de recuperação (30 dias) sem ser restaurada -- elegível pra rotina de purge. */
    public function isEligibleForPurge(int $graceDays, \DateTimeImmutable $now): bool
    {
        if ($this->status !== TrashableStatus::Trashed || $this->anonymizedAt instanceof \DateTimeImmutable || !$this->trashedAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $this->trashedAt <= $now->modify("-{$graceDays} days");
    }

    /**
     * Escruba identificador direto (nome, telefone, endereço, complemento,
     * place id, foto) -- mantém zip/cidade/estado/lat/long, não identificam
     * sozinhos e servem pra estatística agregada. Mesmo espírito de
     * `User::anonymized()`. Quem chama ainda precisa apagar o arquivo da
     * foto do storage -- aqui só solta a referência.
     */
    public function anonymized(): self
    {
        return new self(
            id: $this->id,
            ownerUserId: $this->ownerUserId,
            name: 'Concessionária removida',
            zipCode: $this->zipCode,
            address: '',
            number: '',
            complement: null,
            neighborhood: $this->neighborhood,
            city: $this->city,
            state: $this->state,
            latitude: $this->latitude,
            longitude: $this->longitude,
            googlePlaceId: null,
            phone: null,
            photoFileId: null,
            status: TrashableStatus::Deleted,
            trashedByOwnerDeactivation: $this->trashedByOwnerDeactivation,
            trashedAt: $this->trashedAt,
            anonymizedAt: new \DateTimeImmutable(),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }
}
