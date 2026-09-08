<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\AppointmentStatus;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Shared\Email;

final readonly class PostgresAppointmentRepository implements AppointmentRepository
{
    private const string COLUMNS = 'id, vehicle_id, user_id, scheduled_at, customer_name, customer_email, customer_phone, status, confirmation_token_hash, confirmation_email_sent_at, expires_at, picked_up_at, released_at, created_at, updated_at';

    private const string PREFIXED_COLUMNS = 'a.id, a.vehicle_id, a.user_id, a.scheduled_at, a.customer_name, a.customer_email, a.customer_phone, a.status, a.confirmation_token_hash, a.confirmation_email_sent_at, a.expires_at, a.picked_up_at, a.released_at, a.created_at, a.updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?Appointment
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM appointments WHERE id = :id', ['id' => $id]),
        );
    }

    public function insert(Appointment $appointment): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO appointments (
                id, vehicle_id, user_id, scheduled_at, customer_name, customer_email, customer_phone, status,
                confirmation_token_hash, confirmation_email_sent_at, expires_at, picked_up_at, released_at, created_at, updated_at
            ) VALUES (
                :id, :vehicle_id, :user_id, :scheduled_at, :customer_name, :customer_email, :customer_phone, :status,
                :confirmation_token_hash, :confirmation_email_sent_at, :expires_at, :picked_up_at, :released_at, :created_at, :updated_at
            )
            SQL, $this->toParams($appointment));
    }

    public function update(Appointment $appointment): void
    {
        $params = $this->toParams($appointment);
        unset($params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE appointments SET
                vehicle_id = :vehicle_id, user_id = :user_id, scheduled_at = :scheduled_at,
                customer_name = :customer_name, customer_email = :customer_email, customer_phone = :customer_phone,
                status = :status, confirmation_token_hash = :confirmation_token_hash,
                confirmation_email_sent_at = :confirmation_email_sent_at, expires_at = :expires_at,
                picked_up_at = :picked_up_at, released_at = :released_at, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function findPage(?string $ownerUserId, ?string $status, ?string $vehicleId, int $limit, int $offset): array
    {
        [$where, $params] = $this->criteria($ownerUserId, $status, $vehicleId);
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->hydrateAll($this->connection->execute(sprintf(<<<'SQL'
            SELECT %s FROM appointments a WHERE %s ORDER BY a.scheduled_at DESC, a.id DESC LIMIT :limit OFFSET :offset
            SQL, self::PREFIXED_COLUMNS, $where), $params));
    }

    public function countPage(?string $ownerUserId, ?string $status, ?string $vehicleId): int
    {
        [$where, $params] = $this->criteria($ownerUserId, $status, $vehicleId);

        return (int) $this->connection->execute(sprintf('SELECT COUNT(*) FROM appointments a WHERE %s', $where), $params)->fetchColumn();
    }

    public function findOccupiedStarts(string $vehicleId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $statement = $this->connection->execute(<<<'SQL'
            SELECT scheduled_at FROM appointments
            WHERE vehicle_id = :vehicle_id AND status IN ('pending', 'confirmed')
              AND scheduled_at >= :from AND scheduled_at < :to
            SQL, ['vehicle_id' => $vehicleId, 'from' => $from->format(DATE_ATOM), 'to' => $to->format(DATE_ATOM)]);

        return array_values(array_map(
            static fn (mixed $row): \DateTimeImmutable => Row::from($row)->localDateTime('scheduled_at'),
            $statement->fetchAll(),
        ));
    }

    public function findImmediatelyPreceding(string $vehicleId, \DateTimeImmutable $scheduledAt): ?Appointment
    {
        return $this->hydrateOne($this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM appointments WHERE vehicle_id = :vehicle_id AND scheduled_at < :scheduled_at ORDER BY scheduled_at DESC LIMIT 1',
            ['vehicle_id' => $vehicleId, 'scheduled_at' => $scheduledAt->format(DATE_ATOM)],
        ));
    }

    public function findPendingAwaitingConfirmationEmail(): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM appointments WHERE status = 'pending' AND confirmation_email_sent_at IS NULL",
        ));
    }

    public function findOverduePendingConfirmation(\DateTimeImmutable $now): array
    {
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM appointments WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < :now",
            ['now' => $now->format(DATE_ATOM)],
        ));
    }

    public function findOverdueConfirmed(\DateTimeImmutable $now): array
    {
        // 60 minutos repete `Appointment::DURATION_MINUTES` -- literal aqui porque SQL não importa constante de PHP.
        return $this->hydrateAll($this->connection->execute(
            'SELECT ' . self::COLUMNS . " FROM appointments WHERE status = 'confirmed' AND (scheduled_at + interval '60 minutes') < :now",
            ['now' => $now->format(DATE_ATOM)],
        ));
    }

    /** @return array{0: string, 1: array<string, string|int|null>} */
    private function criteria(?string $ownerUserId, ?string $status, ?string $vehicleId): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if ($ownerUserId !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM vehicles v JOIN dealerships d ON d.id = v.dealership_id WHERE v.id = a.vehicle_id AND d.owner_user_id = :owner_user_id::uuid)';
            $params['owner_user_id'] = $ownerUserId;
        }

        if ($status !== null) {
            $conditions[] = 'a.status = :status';
            $params['status'] = $status;
        }

        if ($vehicleId !== null) {
            $conditions[] = 'a.vehicle_id = :vehicle_id::uuid';
            $params['vehicle_id'] = $vehicleId;
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function hydrateOne(\PDOStatement $statement): ?Appointment
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<Appointment> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): Appointment => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): Appointment
    {
        return new Appointment(
            id: $row->string('id'),
            vehicleId: $row->string('vehicle_id'),
            userId: $row->string('user_id'),
            scheduledAt: $row->localDateTime('scheduled_at'),
            customerName: $row->string('customer_name'),
            customerEmail: new Email($row->string('customer_email')),
            customerPhone: $row->string('customer_phone'),
            status: $row->enum(AppointmentStatus::class, 'status'),
            confirmationTokenHash: $row->nullableString('confirmation_token_hash'),
            confirmationEmailSentAt: $row->nullableLocalDateTime('confirmation_email_sent_at'),
            expiresAt: $row->nullableLocalDateTime('expires_at'),
            pickedUpAt: $row->nullableLocalDateTime('picked_up_at'),
            releasedAt: $row->nullableLocalDateTime('released_at'),
            createdAt: $row->localDateTime('created_at'),
            updatedAt: $row->localDateTime('updated_at'),
        );
    }

    /** @return array<string, string|null> */
    private function toParams(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'vehicle_id' => $appointment->vehicleId,
            'user_id' => $appointment->userId,
            'scheduled_at' => $appointment->scheduledAt->format(DATE_ATOM),
            'customer_name' => $appointment->customerName,
            'customer_email' => $appointment->customerEmail->value,
            'customer_phone' => $appointment->customerPhone,
            'status' => $appointment->status->value,
            'confirmation_token_hash' => $appointment->confirmationTokenHash,
            'confirmation_email_sent_at' => $appointment->confirmationEmailSentAt?->format(DATE_ATOM),
            'expires_at' => $appointment->expiresAt?->format(DATE_ATOM),
            'picked_up_at' => $appointment->pickedUpAt?->format(DATE_ATOM),
            'released_at' => $appointment->releasedAt?->format(DATE_ATOM),
            'created_at' => $appointment->createdAt->format(DATE_ATOM),
            'updated_at' => $appointment->updatedAt->format(DATE_ATOM),
        ];
    }
}
