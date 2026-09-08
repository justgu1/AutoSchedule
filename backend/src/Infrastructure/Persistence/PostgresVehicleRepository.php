<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Money;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;

final readonly class PostgresVehicleRepository implements VehicleRepository
{
    private const string COLUMNS = 'id, dealership_id, brand, model, version, year, price, description, status, trashed_by_dealership_trash, trashed_at, anonymized_at, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?Vehicle
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM vehicles WHERE id = :id', ['id' => $id]),
        );
    }

    public function insert(Vehicle $vehicle): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO vehicles (
                id, dealership_id, brand, model, version, year, price, description, status,
                trashed_by_dealership_trash, trashed_at, anonymized_at, created_at, updated_at
            ) VALUES (
                :id, :dealership_id, :brand, :model, :version, :year, :price, :description, :status,
                :trashed_by_dealership_trash, :trashed_at, :anonymized_at, :created_at, :updated_at
            )
            SQL, $this->toParams($vehicle));
    }

    public function update(Vehicle $vehicle): void
    {
        $params = $this->toParams($vehicle);
        unset($params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                dealership_id = :dealership_id, brand = :brand, model = :model, version = :version,
                year = :year, price = :price, description = :description, status = :status,
                trashed_by_dealership_trash = :trashed_by_dealership_trash,
                trashed_at = :trashed_at, anonymized_at = :anonymized_at, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        return $this->hydrateAll($this->connection->execute(<<<'SQL'
            SELECT v.id, v.dealership_id, v.brand, v.model, v.version, v.year, v.price, v.description, v.status,
                   v.trashed_by_dealership_trash, v.trashed_at, v.anonymized_at, v.created_at, v.updated_at
            FROM vehicles v
            JOIN dealerships d ON d.id = v.dealership_id
            WHERE d.owner_user_id = :owner_user_id AND v.status <> 'deleted'
            ORDER BY v.created_at
            LIMIT :limit OFFSET :offset
            SQL, ['owner_user_id' => $ownerUserId, 'limit' => $limit, 'offset' => $offset]));
    }

    public function countByOwner(string $ownerUserId): int
    {
        return (int) $this->connection->execute(<<<'SQL'
            SELECT COUNT(*)
            FROM vehicles v
            JOIN dealerships d ON d.id = v.dealership_id
            WHERE d.owner_user_id = :owner_user_id AND v.status <> 'deleted'
            SQL, ['owner_user_id' => $ownerUserId])->fetchColumn();
    }

    public function findByDealership(string $dealershipId, int $limit, int $offset): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM vehicles WHERE dealership_id = :dealership_id AND status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
            ['dealership_id' => $dealershipId, 'limit' => $limit, 'offset' => $offset],
        ));
    }

    public function countByDealership(string $dealershipId): int
    {
        return (int) $this->connection->execute(
            "SELECT COUNT(*) FROM vehicles WHERE dealership_id = :dealership_id AND status <> 'deleted'",
            ['dealership_id' => $dealershipId],
        )->fetchColumn();
    }

    public function findPage(int $limit, int $offset): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM vehicles WHERE status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset],
        ));
    }

    public function count(): int
    {
        return (int) $this->connection->execute("SELECT COUNT(*) FROM vehicles WHERE status <> 'deleted'")->fetchColumn();
    }

    public function trash(string $id): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'trashed', trashed_at = now(), trashed_by_dealership_trash = false, updated_at = now()
            WHERE id = :id
            SQL, ['id' => $id]);
    }

    public function restore(string $id): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'active', trashed_at = NULL, trashed_by_dealership_trash = false, updated_at = now()
            WHERE id = :id
            SQL, ['id' => $id]);
    }

    public function findTrashed(): array
    {
        return $this->hydrateAll(
            $this->connection->execute('SELECT ' . self::COLUMNS . " FROM vehicles WHERE status = 'trashed' AND anonymized_at IS NULL"),
        );
    }

    public function purge(Trashable $entity): void
    {
        if ($entity instanceof Vehicle) {
            $this->update($entity);
        }
    }

    public function trashAllInDealership(string $dealershipId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'trashed', trashed_at = now(), trashed_by_dealership_trash = true, updated_at = now()
            WHERE dealership_id = :dealership_id AND status = 'active'
            SQL, ['dealership_id' => $dealershipId]);
    }

    public function restoreAutoTrashedInDealership(string $dealershipId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'active', trashed_at = NULL, trashed_by_dealership_trash = false, updated_at = now()
            WHERE dealership_id = :dealership_id AND status = 'trashed' AND trashed_by_dealership_trash = true
            SQL, ['dealership_id' => $dealershipId]);
    }

    public function trashAllOwnedByUser(string $ownerUserId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'trashed', trashed_at = now(), trashed_by_dealership_trash = true, updated_at = now()
            WHERE status = 'active'
              AND dealership_id IN (SELECT id FROM dealerships WHERE owner_user_id = :owner_user_id)
            SQL, ['owner_user_id' => $ownerUserId]);
    }

    public function restoreAutoTrashedOwnedByUser(string $ownerUserId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'active', trashed_at = NULL, trashed_by_dealership_trash = false, updated_at = now()
            WHERE status = 'trashed' AND trashed_by_dealership_trash = true
              AND dealership_id IN (SELECT id FROM dealerships WHERE owner_user_id = :owner_user_id)
            SQL, ['owner_user_id' => $ownerUserId]);
    }

    private function hydrateOne(\PDOStatement $statement): ?Vehicle
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<Vehicle> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): Vehicle => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): Vehicle
    {
        return new Vehicle(
            id: $row->string('id'),
            dealershipId: $row->string('dealership_id'),
            brand: $row->string('brand'),
            model: $row->string('model'),
            version: $row->nullableString('version'),
            year: $row->nullableInt('year'),
            // `numeric` sai do PDO como string e é assim que fica: converter pra float perderia centavo.
            price: Money::fromDecimal($row->string('price')),
            description: $row->nullableString('description'),
            trash: new TrashState(
                status: $row->enum(TrashableStatus::class, 'status'),
                trashedAt: $row->nullableDateTime('trashed_at'),
                anonymizedAt: $row->nullableDateTime('anonymized_at'),
            ),
            trashedByDealershipTrash: $row->bool('trashed_by_dealership_trash'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|int|bool|null> */
    private function toParams(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'dealership_id' => $vehicle->dealershipId,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'version' => $vehicle->version,
            'year' => $vehicle->year,
            'price' => $vehicle->price->toDecimal(),
            'description' => $vehicle->description,
            'status' => $vehicle->trash->status->value,
            'trashed_by_dealership_trash' => $vehicle->trashedByDealershipTrash,
            'trashed_at' => $vehicle->trash->trashedAt?->format(DATE_ATOM),
            'anonymized_at' => $vehicle->trash->anonymizedAt?->format(DATE_ATOM),
            'created_at' => $vehicle->createdAt->format(DATE_ATOM),
            'updated_at' => $vehicle->updatedAt->format(DATE_ATOM),
        ];
    }
}
