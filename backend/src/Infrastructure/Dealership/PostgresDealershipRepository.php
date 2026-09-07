<?php

declare(strict_types=1);

namespace App\Infrastructure\Dealership;

use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Shared\Address;
use App\Domain\Shared\Email;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Shared\Uf;
use App\Infrastructure\Database\DatabaseConnection;

final readonly class PostgresDealershipRepository implements DealershipRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?Dealership
    {
        $statement = $this->connection->pdo()->prepare('SELECT * FROM dealerships WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function findBySlug(string $slug): ?Dealership
    {
        $statement = $this->connection->pdo()->prepare('SELECT * FROM dealerships WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function insert(Dealership $dealership): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            INSERT INTO dealerships (
                id, owner_user_id, name, slug, zip_code, address, number, complement, neighborhood, city, state,
                phone, email, photo_file_id, status,
                trashed_by_owner_deactivation, trashed_at, anonymized_at, created_at, updated_at
            ) VALUES (
                :id, :owner_user_id, :name, :slug, :zip_code, :address, :number, :complement, :neighborhood, :city, :state,
                :phone, :email, :photo_file_id, :status,
                :trashed_by_owner_deactivation, :trashed_at, :anonymized_at, :created_at, :updated_at
            )
            SQL);

        $statement->execute($this->toParams($dealership));
    }

    /** `slug` normalmente não muda -- só a anonimização (`Dealership::anonymized()`) troca de verdade, o resto reenvia o mesmo valor. */
    public function update(Dealership $dealership): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE dealerships SET
                owner_user_id = :owner_user_id, name = :name, slug = :slug, zip_code = :zip_code, address = :address,
                number = :number, complement = :complement, neighborhood = :neighborhood, city = :city, state = :state,
                phone = :phone, email = :email, photo_file_id = :photo_file_id,
                status = :status, trashed_by_owner_deactivation = :trashed_by_owner_deactivation,
                trashed_at = :trashed_at, anonymized_at = :anonymized_at, updated_at = :updated_at
            WHERE id = :id
            SQL);

        $params = $this->toParams($dealership);
        unset($params['created_at']);
        $statement->execute($params);
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT * FROM dealerships WHERE owner_user_id = :owner_user_id AND status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
        );
        $statement->bindValue('owner_user_id', $ownerUserId);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map($this->fromRow(...), $statement->fetchAll()));
    }

    public function countByOwner(string $ownerUserId): int
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT COUNT(*) FROM dealerships WHERE owner_user_id = :owner_user_id AND status <> 'deleted'",
        );
        $statement->execute(['owner_user_id' => $ownerUserId]);

        return (int) $statement->fetchColumn();
    }

    public function findPage(int $limit, int $offset): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT * FROM dealerships WHERE status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map($this->fromRow(...), $statement->fetchAll()));
    }

    public function count(): int
    {
        return (int) $this->connection->pdo()->query("SELECT COUNT(*) FROM dealerships WHERE status <> 'deleted'")->fetchColumn();
    }

    public function trash(string $id, bool $byOwnerDeactivation): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE dealerships SET
                status = 'trashed', trashed_at = now(), trashed_by_owner_deactivation = :by_owner_deactivation, updated_at = now()
            WHERE id = :id
            SQL);
        $statement->execute(['id' => $id, 'by_owner_deactivation' => $byOwnerDeactivation ? 't' : 'f']);
    }

    public function restore(string $id): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE dealerships SET
                status = 'active', trashed_at = NULL, trashed_by_owner_deactivation = false, updated_at = now()
            WHERE id = :id
            SQL);
        $statement->execute(['id' => $id]);
    }

    public function findTrashed(): array
    {
        $statement = $this->connection->pdo()->prepare("SELECT * FROM dealerships WHERE status = 'trashed' AND anonymized_at IS NULL");
        $statement->execute();

        return array_values(array_map($this->fromRow(...), $statement->fetchAll()));
    }

    public function purge(Trashable $entity): void
    {
        if ($entity instanceof Dealership) {
            $this->update($entity);
        }
    }

    public function trashAllOwnedBy(string $ownerUserId): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE dealerships SET
                status = 'trashed', trashed_at = now(), trashed_by_owner_deactivation = true, updated_at = now()
            WHERE owner_user_id = :owner_user_id AND status = 'active'
            SQL);
        $statement->execute(['owner_user_id' => $ownerUserId]);
    }

    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE dealerships SET
                status = 'active', trashed_at = NULL, trashed_by_owner_deactivation = false, updated_at = now()
            WHERE owner_user_id = :owner_user_id AND status = 'trashed' AND trashed_by_owner_deactivation = true
            SQL);
        $statement->execute(['owner_user_id' => $ownerUserId]);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): Dealership
    {
        return new Dealership(
            id: $row['id'],
            ownerUserId: $row['owner_user_id'],
            name: $row['name'],
            slug: $row['slug'],
            address: new Address(
                zipCode: $row['zip_code'],
                street: $row['address'],
                number: $row['number'],
                complement: $row['complement'],
                neighborhood: $row['neighborhood'],
                city: $row['city'],
                state: Uf::from($row['state']),
            ),
            phone: $row['phone'],
            email: Email::fromNullable($row['email']),
            photoFileId: $row['photo_file_id'],
            trash: new TrashState(
                status: TrashableStatus::from($row['status']),
                trashedAt: $this->toDateTime($row['trashed_at']),
                anonymizedAt: $this->toDateTime($row['anonymized_at']),
            ),
            trashedByOwnerDeactivation: (bool) $row['trashed_by_owner_deactivation'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    private function toDateTime(?string $value): ?\DateTimeImmutable
    {
        return $value === null ? null : new \DateTimeImmutable($value);
    }

    /** @return array<string, mixed> */
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
            'trashed_by_owner_deactivation' => $dealership->trashedByOwnerDeactivation ? 't' : 'f',
            'trashed_at' => $dealership->trash->trashedAt?->format(DATE_ATOM),
            'anonymized_at' => $dealership->trash->anonymizedAt?->format(DATE_ATOM),
            'created_at' => $dealership->createdAt->format(DATE_ATOM),
            'updated_at' => $dealership->updatedAt->format(DATE_ATOM),
        ];
    }
}
