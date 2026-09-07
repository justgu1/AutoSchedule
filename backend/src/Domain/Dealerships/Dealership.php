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
        public string $slug,
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
        public ?string $email,
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
        ?string $email = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $googlePlaceId = null,
    ): self {
        $now = new \DateTimeImmutable();
        $id = Uuid::v7();

        return new self(
            id: $id,
            ownerUserId: $ownerUserId,
            name: $name,
            slug: self::buildSlug($name, $id),
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
            email: $email,
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
        ?string $email,
        ?float $latitude,
        ?float $longitude,
        ?string $googlePlaceId,
    ): self {
        return new self(
            id: $this->id,
            ownerUserId: $this->ownerUserId,
            name: $name,
            slug: $this->slug,
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
            email: $email,
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
            slug: $this->slug,
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
            email: $this->email,
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
            slug: $this->slug,
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
            email: $this->email,
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
     * `User::anonymized()`. `slug` também é trocado -- ele nasce do nome, uma
     * URL pública antiga não pode continuar divulgando o nome do negócio
     * removido. Quem chama ainda precisa apagar o arquivo da foto do
     * storage -- aqui só solta a referência.
     */
    public function anonymized(): self
    {
        return new self(
            id: $this->id,
            ownerUserId: $this->ownerUserId,
            name: 'Concessionária removida',
            slug: self::buildSlug('concessionaria removida', $this->id),
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
            email: null,
            photoFileId: null,
            status: TrashableStatus::Deleted,
            trashedByOwnerDeactivation: $this->trashedByOwnerDeactivation,
            trashedAt: $this->trashedAt,
            anonymizedAt: new \DateTimeImmutable(),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    /**
     * Slugify do nome + 6 caracteres do próprio id -- os ÚLTIMOS, não os
     * primeiros: UUID v7 tem timestamp nos bits iniciais, então duas
     * concessionárias criadas perto uma da outra no tempo (bem comum em
     * teste, ou só em uso normal) teriam o mesmo prefixo e colidiriam de
     * verdade toda vez, não só em teoria. Os últimos caracteres são a parte
     * aleatória. Ainda não elimina colisão 100% (a fatia perde entropia),
     * mas o `UNIQUE` da coluna é o backstop: colidir é raro o bastante pra
     * não valer complexidade de retry, e um erro nesse caso extremo é aceitável.
     */
    private static function buildSlug(string $name, string $id): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $transliterated !== false ? $transliterated : $name), '-'));
        $suffix = substr(str_replace('-', '', $id), -6);

        return ($base !== '' ? $base : 'concessionaria') . '-' . $suffix;
    }
}
