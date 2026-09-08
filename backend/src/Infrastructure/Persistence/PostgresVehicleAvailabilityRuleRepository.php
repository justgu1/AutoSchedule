<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;
use App\Domain\Availability\WeeklyWindow;

final readonly class PostgresVehicleAvailabilityRuleRepository implements VehicleAvailabilityRuleRepository
{
    private const string COLUMNS = 'id, vehicle_id, weekday, start_time, end_time, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?VehicleAvailabilityRule
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM vehicle_availability_rules WHERE id = :id', ['id' => $id]),
        );
    }

    public function insert(VehicleAvailabilityRule $rule): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO vehicle_availability_rules (id, vehicle_id, weekday, start_time, end_time, created_at, updated_at)
            VALUES (:id, :vehicle_id, :weekday, :start_time, :end_time, :created_at, :updated_at)
            SQL, $this->toParams($rule));
    }

    public function update(VehicleAvailabilityRule $rule): void
    {
        $params = $this->toParams($rule);
        unset($params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE vehicle_availability_rules
            SET weekday = :weekday, start_time = :start_time, end_time = :end_time, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function delete(string $id): void
    {
        $this->connection->execute('DELETE FROM vehicle_availability_rules WHERE id = :id', ['id' => $id]);
    }

    public function findByVehicle(string $vehicleId): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM vehicle_availability_rules WHERE vehicle_id = :vehicle_id ORDER BY weekday, start_time',
            ['vehicle_id' => $vehicleId],
        ));
    }

    private function hydrateOne(\PDOStatement $statement): ?VehicleAvailabilityRule
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<VehicleAvailabilityRule> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): VehicleAvailabilityRule => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): VehicleAvailabilityRule
    {
        return new VehicleAvailabilityRule(
            id: $row->string('id'),
            vehicleId: $row->string('vehicle_id'),
            window: new WeeklyWindow($row->int('weekday'), $row->time('start_time'), $row->time('end_time')),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|int> */
    private function toParams(VehicleAvailabilityRule $rule): array
    {
        return [
            'id' => $rule->id,
            'vehicle_id' => $rule->vehicleId,
            'weekday' => $rule->window->weekday,
            'start_time' => $rule->window->startTime->format('H:i:s'),
            'end_time' => $rule->window->endTime->format('H:i:s'),
            'created_at' => $rule->createdAt->format(DATE_ATOM),
            'updated_at' => $rule->updatedAt->format(DATE_ATOM),
        ];
    }
}
