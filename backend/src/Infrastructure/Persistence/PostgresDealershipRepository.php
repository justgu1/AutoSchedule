<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Shared\Address;
use App\Domain\Shared\Email;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Shared\Uf;

final readonly class PostgresDealershipRepository implements DealershipRepository
{
    private const string COLUMNS = 'id, owner_user_id, name, slug, zip_code, address, number, complement, neighborhood, city, state, phone, email, photo_file_id, status, trashed_by_owner_deactivation, trashed_at, anonymized_at, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?Dealership
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM dealerships WHERE id = :id', ['id' => $id]),
        );
    }

    public function findBySlug(string $slug): ?Dealership
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM dealerships WHERE slug = :slug', ['slug' => $slug]),
        );
    }

    public function insert(Dealership $dealership): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO dealerships (
                id, owner_user_id, name, slug, zip_code, address, number, complement, neighborhood, city, state,
                phone, email, photo_file_id, status,
                trashed_by_owner_deactivation, trashed_at, anonymized_at, created_at, updated_at
            ) VALUES (
                :id, :owner_user_id, :name, :slug, :zip_code, :address, :number, :complement, :neighborhood, :city, :state,
                :phone, :email, :photo_file_id, :status,
                :trashed_by_owner_deactivation, :trashed_at, :anonymized_at, :created_at, :updated_at
            )
            SQL, $this->toParams($dealership));
    }

    /** `slug` normalmente não muda -- só a anonimização (`Dealership::anonymized()`) troca de verdade, o resto reenvia o mesmo valor. */
    public function update(Dealership $dealership): void
    {
        $params = $this->toParams($dealership);
        unset($params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE dealerships SET
                owner_user_id = :owner_user_id, name = :name, slug = :slug, zip_code = :zip_code, address = :address,
                number = :number, complement = :complement, neighborhood = :neighborhood, city = :city, state = :state,
                phone = :phone, email = :email, photo_file_id = :photo_file_id,
                status = :status, trashed_by_owner_deactivation = :trashed_by_owner_deactivation,
                trashed_at = :trashed_at, anonymized_at = :anonymized_at, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM dealerships WHERE owner_user_id = :owner_user_id AND status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
            ['owner_user_id' => $ownerUserId, 'limit' => $limit, 'offset' => $offset],
        ));
    }

    public function countByOwner(string $ownerUserId): int
    {
        $statement = $this->connection->execute(
            "SELECT COUNT(*) FROM dealerships WHERE owner_user_id = :owner_user_id AND status <> 'deleted'",
            ['owner_user_id' => $ownerUserId],
        );

        return (int) $statement->fetchColumn();
    }

    public function findPage(int $limit, int $offset): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM dealerships WHERE status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset],
        ));
    }

    public function count(): int
    {
        return (int) $this->connection->execute("SELECT COUNT(*) FROM dealerships WHERE status <> 'deleted'")->fetchColumn();
    }

    public function trash(string $id): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE dealerships SET
                status = 'trashed', trashed_at = now(), trashed_by_owner_deactivation = false, updated_at = now()
            WHERE id = :id
            SQL, ['id' => $id]);
    }

    public function restore(string $id): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE dealerships SET
                status = 'active', trashed_at = NULL, trashed_by_owner_deactivation = false, updated_at = now()
            WHERE id = :id
            SQL, ['id' => $id]);
    }

    public function findTrashed(): array
    {
        return $this->hydrateAll(
            $this->connection->execute('SELECT ' . self::COLUMNS . " FROM dealerships WHERE status = 'trashed' AND anonymized_at IS NULL"),
        );
    }

    public function purge(Trashable $entity): void
    {
        if ($entity instanceof Dealership) {
            $this->update($entity);
        }
    }

    public function trashAllOwnedBy(string $ownerUserId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE dealerships SET
                status = 'trashed', trashed_at = now(), trashed_by_owner_deactivation = true, updated_at = now()
            WHERE owner_user_id = :owner_user_id AND status = 'active'
            SQL, ['owner_user_id' => $ownerUserId]);
    }

    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE dealerships SET
                status = 'active', trashed_at = NULL, trashed_by_owner_deactivation = false, updated_at = now()
            WHERE owner_user_id = :owner_user_id AND status = 'trashed' AND trashed_by_owner_deactivation = true
            SQL, ['owner_user_id' => $ownerUserId]);
    }

    private function hydrateOne(\PDOStatement $statement): ?Dealership
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<Dealership> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): Dealership => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): Dealership
    {
        return new Dealership(
            id: $row->string('id'),
            ownerUserId: $row->string('owner_user_id'),
            name: $row->string('name'),
            slug: $row->string('slug'),
            address: new Address(
                zipCode: $row->string('zip_code'),
                street: $row->string('address'),
                number: $row->string('number'),
                complement: $row->nullableString('complement'),
                neighborhood: $row->string('neighborhood'),
                city: $row->string('city'),
                state: $row->enum(Uf::class, 'state'),
            ),
            phone: $row->nullableString('phone'),
            email: Email::fromNullable($row->nullableString('email')),
            photoFileId: $row->nullableString('photo_file_id'),
            trash: new TrashState(
                status: $row->enum(TrashableStatus::class, 'status'),
                trashedAt: $row->nullableDateTime('trashed_at'),
                anonymizedAt: $row->nullableDateTime('anonymized_at'),
            ),
            trashedByOwnerDeactivation: $row->bool('trashed_by_owner_deactivation'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|bool|null> */
    private function toParams(Dealership $dealership): array
    {
        return [
            'id' => $dealership->id,
            'owner_user_id' => $dealership->ownerUserId,
            'name' => $dealership->name,
            'slug' => $dealership->slug,
            'zip_code' => $dealership->address->zipCode,
            'address' => $dealership->address->street,
            'number' => $dealership->address->number,
            'complement' => $dealership->address->complement,
            'neighborhood' => $dealership->address->neighborhood,
            'city' => $dealership->address->city,
            'state' => $dealership->address->state->value,
            'phone' => $dealership->phone,
            'email' => $dealership->email?->value,
            'photo_file_id' => $dealership->photoFileId,
            'status' => $dealership->trash->status->value,
            'trashed_by_owner_deactivation' => $dealership->trashedByOwnerDeactivation,
            'trashed_at' => $dealership->trash->trashedAt?->format(DATE_ATOM),
            'anonymized_at' => $dealership->trash->anonymizedAt?->format(DATE_ATOM),
            'created_at' => $dealership->createdAt->format(DATE_ATOM),
            'updated_at' => $dealership->updatedAt->format(DATE_ATOM),
        ];
    }
}
