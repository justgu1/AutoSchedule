<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Vehicle\Ports\VehicleImageRepository;
use App\Domain\Vehicle\VehicleImage;

final readonly class PostgresVehicleImageRepository implements VehicleImageRepository
{
    private const string COLUMNS = 'id, vehicle_id, file_id, position, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findByVehicle(string $vehicleId): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM vehicle_images WHERE vehicle_id = :vehicle_id ORDER BY position',
            ['vehicle_id' => $vehicleId],
        ));
    }

    public function findById(string $id): ?VehicleImage
    {
        $row = $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM vehicle_images WHERE id = :id', ['id' => $id])->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    public function findCoversFor(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $images = $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM vehicle_images WHERE position = 0 AND vehicle_id = ANY(:vehicle_ids::uuid[])',
            ['vehicle_ids' => PostgresArray::toText($vehicleIds)],
        ));

        $covers = [];

        foreach ($images as $image) {
            $covers[$image->vehicleId] = $image;
        }

        return $covers;
    }

    public function insert(VehicleImage $image): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO vehicle_images (id, vehicle_id, file_id, position, created_at, updated_at)
            VALUES (:id, :vehicle_id, :file_id, :position, :created_at, :updated_at)
            SQL, [
            'id' => $image->id,
            'vehicle_id' => $image->vehicleId,
            'file_id' => $image->fileId,
            'position' => $image->position,
            'created_at' => $image->createdAt->format(DATE_ATOM),
            'updated_at' => $image->updatedAt->format(DATE_ATOM),
        ]);
    }

    public function delete(string $id): void
    {
        $this->connection->execute('DELETE FROM vehicle_images WHERE id = :id', ['id' => $id]);
    }

    public function deleteAllForVehicle(string $vehicleId): void
    {
        $this->connection->execute('DELETE FROM vehicle_images WHERE vehicle_id = :vehicle_id', ['vehicle_id' => $vehicleId]);
    }

    public function nextPosition(string $vehicleId): int
    {
        // `FOR UPDATE` na linha do veículo: dois lotes concorrentes leriam o mesmo MAX e um violaria o UNIQUE.
        $this->connection->execute('SELECT 1 FROM vehicles WHERE id = :vehicle_id FOR UPDATE', ['vehicle_id' => $vehicleId]);

        $statement = $this->connection->execute(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM vehicle_images WHERE vehicle_id = :vehicle_id',
            ['vehicle_id' => $vehicleId],
        );

        return (int) $statement->fetchColumn();
    }

    public function reorder(string $vehicleId, array $imageIdsInOrder): void
    {
        $this->connection->execute(
            'UPDATE vehicle_images SET position = -1 - position WHERE vehicle_id = :vehicle_id',
            ['vehicle_id' => $vehicleId],
        );

        $params = ['vehicle_id' => $vehicleId];
        $values = [];

        foreach ($imageIdsInOrder as $position => $imageId) {
            $params["id_{$position}"] = $imageId;
            $values[] = "(:id_{$position}::uuid, {$position})";
        }

        $this->connection->execute(sprintf(<<<'SQL'
            UPDATE vehicle_images vi
            SET position = novo.position, updated_at = now()
            FROM (VALUES %s) AS novo(id, position)
            WHERE vi.id = novo.id AND vi.vehicle_id = :vehicle_id
            SQL, implode(', ', $values)), $params);
    }

    /** @return list<VehicleImage> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): VehicleImage => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): VehicleImage
    {
        return new VehicleImage(
            id: $row->string('id'),
            vehicleId: $row->string('vehicle_id'),
            fileId: $row->string('file_id'),
            position: $row->int('position'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }
}
