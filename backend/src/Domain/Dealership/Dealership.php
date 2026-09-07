<?php

declare(strict_types=1);

namespace App\Domain\Dealership;

use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashState;
use App\Domain\Shared\Uuid;

final readonly class Dealership implements Trashable
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
        public TrashState $trash,
        public bool $trashedByOwnerDeactivation,
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
            trash: new TrashState(),
            trashedByOwnerDeactivation: false,
            createdAt: $now,
            updatedAt: $now,
        );
    }

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
        return clone($this, [
            'name' => $name,
            'zipCode' => $zipCode,
            'address' => $address,
            'number' => $number,
            'complement' => $complement,
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => $state,
            'phone' => $phone,
            'email' => $email,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'googlePlaceId' => $googlePlaceId,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    /** Admin reassociando a concessionária a outro seller. */
    public function withOwner(string $ownerUserId): self
    {
        return clone($this, ['ownerUserId' => $ownerUserId, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /** Só uma foto por concessionária -- setar substitui a anterior (quem chama cuida de remover o arquivo velho do storage). */
    public function withPhoto(?string $photoFileId): self
    {
        return clone($this, ['photoFileId' => $photoFileId, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /**
     * Mesmo espírito de User::anonymized(): cai o que identifica direto, fica o que só serve agregado.
     * O slug troca junto porque nasce do nome, e URL pública antiga não pode seguir divulgando o negócio.
     */
    public function anonymized(): static
    {
        return clone($this, [
            'name' => 'Concessionária removida',
            'slug' => self::buildSlug('concessionaria removida', $this->id),
            'address' => '',
            'number' => '',
            'complement' => null,
            'googlePlaceId' => null,
            'phone' => null,
            'email' => null,
            'photoFileId' => null,
            'trash' => $this->trash->anonymized(),
        ]);
    }

    /**
     * Os ÚLTIMOS 6 caracteres do id, não os primeiros: em UUIDv7 o começo é timestamp e colidiria entre
     * criações próximas. O UNIQUE da coluna é o backstop pro resíduo de colisão, sem retry.
     */
    private static function buildSlug(string $name, string $id): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $transliterated !== false ? $transliterated : $name), '-'));
        $suffix = substr(str_replace('-', '', $id), -6);

        return ($base !== '' ? $base : 'concessionaria') . '-' . $suffix;
    }
}
