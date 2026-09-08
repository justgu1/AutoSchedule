<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Availability\DealershipAvailabilityRule;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;
use App\Domain\Availability\WeeklyWindow;

final readonly class PostgresDealershipAvailabilityRuleRepository implements DealershipAvailabilityRuleRepository
{
    private const string COLUMNS = 'id, dealership_id, weekday, start_time, end_time, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?DealershipAvailabilityRule
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM dealership_availability_rules WHERE id = :id', ['id' => $id]),
        );
    }

    public function insert(DealershipAvailabilityRule $rule): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO dealership_availability_rules (id, dealership_id, weekday, start_time, end_time, created_at, updated_at)
            VALUES (:id, :dealership_id, :weekday, :start_time, :end_time, :created_at, :updated_at)
            SQL, $this->toParams($rule));
    }

    public function update(DealershipAvailabilityRule $rule): void
    {
        $params = $this->toParams($rule);
        unset($params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE dealership_availability_rules
            SET weekday = :weekday, start_time = :start_time, end_time = :end_time, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function delete(string $id): void
    {
        $this->connection->execute('DELETE FROM dealership_availability_rules WHERE id = :id', ['id' => $id]);
    }

    public function findByDealership(string $dealershipId): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM dealership_availability_rules WHERE dealership_id = :dealership_id ORDER BY weekday, start_time',
            ['dealership_id' => $dealershipId],
        ));
    }

    private function hydrateOne(\PDOStatement $statement): ?DealershipAvailabilityRule
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<DealershipAvailabilityRule> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): DealershipAvailabilityRule => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): DealershipAvailabilityRule
    {
        return new DealershipAvailabilityRule(
            id: $row->string('id'),
            dealershipId: $row->string('dealership_id'),
            window: new WeeklyWindow($row->int('weekday'), $row->time('start_time'), $row->time('end_time')),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|int> */
    private function toParams(DealershipAvailabilityRule $rule): array
    {
        return [
            'id' => $rule->id,
            'dealership_id' => $rule->dealershipId,
            'weekday' => $rule->window->weekday,
            'start_time' => $rule->window->startTime->format('H:i:s'),
            'end_time' => $rule->window->endTime->format('H:i:s'),
            'created_at' => $rule->createdAt->format(DATE_ATOM),
            'updated_at' => $rule->updatedAt->format(DATE_ATOM),
        ];
    }
}
