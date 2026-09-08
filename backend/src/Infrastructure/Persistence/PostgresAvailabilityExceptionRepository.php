<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;

final readonly class PostgresAvailabilityExceptionRepository implements AvailabilityExceptionRepository
{
    private const string COLUMNS = 'id, dealership_id, vehicle_id, date, start_time, end_time, is_available, reason, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?AvailabilityException
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM availability_exceptions WHERE id = :id', ['id' => $id]),
        );
    }

    public function insert(AvailabilityException $exception): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO availability_exceptions (id, dealership_id, vehicle_id, date, start_time, end_time, is_available, reason, created_at, updated_at)
            VALUES (:id, :dealership_id, :vehicle_id, :date, :start_time, :end_time, :is_available, :reason, :created_at, :updated_at)
            SQL, $this->toParams($exception));
    }

    public function update(AvailabilityException $exception): void
    {
        $params = $this->toParams($exception);
        unset($params['created_at'], $params['dealership_id'], $params['vehicle_id']);

        $this->connection->execute(<<<'SQL'
            UPDATE availability_exceptions
            SET date = :date, start_time = :start_time, end_time = :end_time, is_available = :is_available,
                reason = :reason, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function delete(string $id): void
    {
        $this->connection->execute('DELETE FROM availability_exceptions WHERE id = :id', ['id' => $id]);
    }

    public function findByDealership(string $dealershipId): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM availability_exceptions WHERE dealership_id = :dealership_id ORDER BY date',
            ['dealership_id' => $dealershipId],
        ));
    }

    public function findByVehicle(string $vehicleId): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM availability_exceptions WHERE vehicle_id = :vehicle_id ORDER BY date',
            ['vehicle_id' => $vehicleId],
        ));
    }

    public function findByDealershipInRange(string $dealershipId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM availability_exceptions WHERE dealership_id = :dealership_id AND date BETWEEN :from AND :to ORDER BY date',
            ['dealership_id' => $dealershipId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        ));
    }

    public function findByVehicleInRange(string $vehicleId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM availability_exceptions WHERE vehicle_id = :vehicle_id AND date BETWEEN :from AND :to ORDER BY date',
            ['vehicle_id' => $vehicleId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        ));
    }

    private function hydrateOne(\PDOStatement $statement): ?AvailabilityException
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<AvailabilityException> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): AvailabilityException => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): AvailabilityException
    {
        return new AvailabilityException(
            id: $row->string('id'),
            dealershipId: $row->nullableString('dealership_id'),
            vehicleId: $row->nullableString('vehicle_id'),
            date: $row->dateTime('date'),
            startTime: $row->nullableTime('start_time'),
            endTime: $row->nullableTime('end_time'),
            isAvailable: $row->bool('is_available'),
            reason: $row->nullableString('reason'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|bool|null> */
    private function toParams(AvailabilityException $exception): array
    {
        return [
            'id' => $exception->id,
            'dealership_id' => $exception->dealershipId,
            'vehicle_id' => $exception->vehicleId,
            'date' => $exception->date->format('Y-m-d'),
            'start_time' => $exception->startTime?->format('H:i:s'),
            'end_time' => $exception->endTime?->format('H:i:s'),
            'is_available' => $exception->isAvailable,
            'reason' => $exception->reason,
            'created_at' => $exception->createdAt->format(DATE_ATOM),
            'updated_at' => $exception->updatedAt->format(DATE_ATOM),
        ];
    }
}
