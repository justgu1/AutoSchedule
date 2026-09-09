<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Vehicle\Amenity;
use App\Domain\Vehicle\Ports\VehicleAmenityCatalog;

final readonly class PostgresVehicleAmenityCatalog implements VehicleAmenityCatalog
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function all(): array
    {
        $statement = $this->connection->execute('SELECT id, code, label, position FROM vehicle_amenity_catalog ORDER BY position');

        return array_values(array_map(
            static fn (mixed $row): Amenity => self::fromRow(Row::from($row)),
            $statement->fetchAll(),
        ));
    }

    public function existingIds(array $ids): array
    {
        $unique = array_values(array_unique($ids));

        if ($unique === []) {
            return [];
        }

        $statement = $this->connection->execute(
            'SELECT id FROM vehicle_amenity_catalog WHERE id = ANY(:ids::uuid[])',
            ['ids' => PostgresArray::toText($unique)],
        );

        return array_values(array_map(static fn (mixed $row): string => Row::from($row)->string('id'), $statement->fetchAll()));
    }

    private static function fromRow(Row $row): Amenity
    {
        return new Amenity(
            id: $row->string('id'),
            code: $row->string('code'),
            label: $row->string('label'),
            position: $row->int('position'),
        );
    }
}
