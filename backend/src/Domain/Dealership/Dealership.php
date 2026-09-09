<?php

declare(strict_types=1);

namespace App\Domain\Dealership;

use App\Domain\Shared\Address;
use App\Domain\Shared\Email;
use App\Domain\Shared\Slugger;
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
        public Address $address,
        public ?string $phone,
        public ?Email $email,
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
        Address $address,
        ?string $phone,
        ?Email $email = null,
    ): self {
        $now = new \DateTimeImmutable();
        $id = Uuid::v7();

        return new self(
            id: $id,
            ownerUserId: $ownerUserId,
            name: $name,
            slug: self::buildSlug($name, $id),
            address: $address,
            phone: $phone,
            email: $email,
            photoFileId: null,
            trash: new TrashState(),
            trashedByOwnerDeactivation: false,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    #[\NoDiscard]
    public function withProfile(string $name, Address $address, ?string $phone, ?Email $email): self
    {
        return clone($this, [
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    /** Admin reassociando a concessionária a outro seller. */
    #[\NoDiscard]
    public function withOwner(string $ownerUserId): self
    {
        return clone($this, ['ownerUserId' => $ownerUserId, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /** Só uma foto por concessionária -- setar substitui a anterior (quem chama cuida de remover o arquivo velho do storage). */
    #[\NoDiscard]
    public function withPhoto(?string $photoFileId): self
    {
        return clone($this, ['photoFileId' => $photoFileId, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /**
     * Mesmo espírito de User::anonymized(): cai o que identifica direto, fica o que só serve agregado.
     * O slug troca junto porque nasce do nome, e URL pública antiga não pode seguir divulgando o negócio.
     */
    #[\NoDiscard]
    public function anonymized(): static
    {
        return clone($this, [
            'name' => 'Concessionária removida',
            'slug' => self::buildSlug('concessionaria removida', $this->id),
            'address' => $this->address->withoutStreetLevelDetail(),
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
        $base = Slugger::slugify($name);
        $suffix = substr(str_replace('-', '', $id), -6);

        return ($base !== '' ? $base : 'concessionaria') . '-' . $suffix;
    }
}
