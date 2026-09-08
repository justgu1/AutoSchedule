<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Vehicle\Ports\VehicleAmenityLinkRepository;

final readonly class PostgresVehicleAmenityLinkRepository implements VehicleAmenityLinkRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function amenityIdsFor(string $vehicleId): array
    {
        $statement = $this->connection->execute(
            'SELECT amenity_id FROM vehicle_amenity_links WHERE vehicle_id = :vehicle_id',
            ['vehicle_id' => $vehicleId],
        );

        return array_values(array_map(static fn (mixed $row): string => Row::from($row)->string('amenity_id'), $statement->fetchAll()));
    }

    public function replace(string $vehicleId, array $amenityIds): void
    {
        $this->connection->execute('DELETE FROM vehicle_amenity_links WHERE vehicle_id = :vehicle_id', ['vehicle_id' => $vehicleId]);

        if ($amenityIds === []) {
            return;
        }

        $params = ['vehicle_id' => $vehicleId];
        $values = [];

        foreach (array_values(array_unique($amenityIds)) as $index => $amenityId) {
            $params["amenity_id_{$index}"] = $amenityId;
            $values[] = "(:vehicle_id::uuid, :amenity_id_{$index}::uuid)";
        }

        $this->connection->execute(
            sprintf('INSERT INTO vehicle_amenity_links (vehicle_id, amenity_id) VALUES %s', implode(', ', $values)),
            $params,
        );
    }
}
