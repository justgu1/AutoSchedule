<?php

declare(strict_types=1);

namespace App\Infrastructure\User;

use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Infrastructure\Database\DatabaseConnection;

final readonly class PostgresUserRepository implements UserRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?User
    {
        return $this->findOneBy('id', $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy('email', $email);
    }

    public function existsByEmail(string $email): bool
    {
        $statement = $this->connection->pdo()->prepare("SELECT 1 FROM users WHERE email = :email AND status <> 'deleted'");
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function insert(User $user): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            INSERT INTO users (id, name, email, phone, password, role, password_set_at, email_verified_at, created_at, updated_at)
            VALUES (:id, :name, :email, :phone, :password, :role, :password_set_at, :email_verified_at, :created_at, :updated_at)
            SQL);

        $statement->execute($this->toParams($user));
    }

    public function update(User $user): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE users SET
                name = :name, email = :email, phone = :phone, password = :password,
                role = :role, password_set_at = :password_set_at, email_verified_at = :email_verified_at,
                updated_at = :updated_at
            WHERE id = :id
            SQL);

        $params = $this->toParams($user);
        unset($params['created_at']);
        $statement->execute($params);
    }

    public function trash(string $id): void
    {
        $statement = $this->connection->pdo()->prepare(
            "UPDATE users SET status = 'trashed', deleted_at = now(), updated_at = now() WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->connection->pdo()->prepare(
            "UPDATE users SET status = 'active', deleted_at = NULL, updated_at = now() WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    public function findTrashed(): array
    {
        $statement = $this->connection->pdo()->prepare("SELECT * FROM users WHERE status = 'trashed' AND anonymized_at IS NULL");
        $statement->execute();

        return array_values(array_map($this->fromRow(...), $statement->fetchAll()));
    }

    public function purge(Trashable $entity): void
    {
        if (!$entity instanceof User) {
            return;
        }

        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            UPDATE users
            SET name = :name, email = :email, phone = NULL, status = :status,
                anonymized_at = :anonymized_at, deleted_at = COALESCE(deleted_at, now()), updated_at = now()
            WHERE id = :id
            SQL);
        $statement->execute([
            'id' => $entity->id,
            'name' => $entity->name,
            'email' => $entity->email,
            'status' => $entity->trash->status->value,
            'anonymized_at' => $entity->trash->anonymizedAt?->format(DATE_ATOM),
        ]);
    }

    public function findPage(int $limit, int $offset): array
    {
        $statement = $this->connection->pdo()->prepare(
            "SELECT * FROM users WHERE status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->fromRow(...), $statement->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->connection->pdo()->query("SELECT COUNT(*) FROM users WHERE status <> 'deleted'")->fetchColumn();
    }

    public function countByRole(UserRole $role): int
    {
        $statement = $this->connection->pdo()->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND status = 'active'");
        $statement->execute(['role' => $role->value]);

        return (int) $statement->fetchColumn();
    }

    private function findOneBy(string $column, string $value): ?User
    {
        $statement = $this->connection->pdo()->prepare("SELECT * FROM users WHERE {$column} = :value AND status <> 'deleted'");
        $statement->execute(['value' => $value]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): User
    {
        return new User(
            id: $row['id'],
            name: $row['name'],
            email: $row['email'],
            phone: $row['phone'],
            passwordHash: $row['password'],
            role: UserRole::from($row['role']),
            passwordSetAt: $this->toDateTime($row['password_set_at']),
            emailVerifiedAt: $this->toDateTime($row['email_verified_at']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            trash: new TrashState(
                status: TrashableStatus::from($row['status']),
                trashedAt: $this->toDateTime($row['deleted_at']),
                anonymizedAt: $this->toDateTime($row['anonymized_at']),
            ),
        );
    }

    private function toDateTime(?string $value): ?\DateTimeImmutable
    {
        return $value === null ? null : new \DateTimeImmutable($value);
    }

    /** @return array<string, mixed> */
    private function toParams(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $user->passwordHash,
            'role' => $user->role->value,
            'password_set_at' => $user->passwordSetAt?->format(DATE_ATOM),
            'email_verified_at' => $user->emailVerifiedAt?->format(DATE_ATOM),
            'created_at' => $user->createdAt->format(DATE_ATOM),
            'updated_at' => $user->updatedAt->format(DATE_ATOM),
        ];
    }
}
