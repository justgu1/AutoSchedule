<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Email;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;

final readonly class PostgresUserRepository implements UserRepository
{
    private const string COLUMNS = 'id, name, email, phone, password, role, password_set_at, email_verified_at, created_at, updated_at, status, deleted_at, anonymized_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?User
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::COLUMNS . " FROM users WHERE id = :id AND status <> 'deleted'",
        );
        $statement->execute(['id' => $id]);

        return $this->hydrateOne($statement);
    }

    public function findByEmail(Email $email): ?User
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::COLUMNS . " FROM users WHERE email = :email AND status <> 'deleted'",
        );
        $statement->execute(['email' => $email->value]);

        return $this->hydrateOne($statement);
    }

    public function existsByEmail(Email $email): bool
    {
        $statement = $this->connection->pdo()->prepare("SELECT 1 FROM users WHERE email = :email AND status <> 'deleted'");
        $statement->execute(['email' => $email->value]);

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
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::COLUMNS . " FROM users WHERE status = 'trashed' AND anonymized_at IS NULL",
        );
        $statement->execute();

        return $this->hydrateAll($statement);
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
            'email' => $entity->email->value,
            'status' => $entity->trash->status->value,
            'anonymized_at' => $entity->trash->anonymizedAt?->format(DATE_ATOM),
        ]);
    }

    public function findPage(int $limit, int $offset): array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . self::COLUMNS . " FROM users WHERE status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $this->hydrateAll($statement);
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

    private function hydrateOne(\PDOStatement $statement): ?User
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<User> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): User => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): User
    {
        return new User(
            id: $row->string('id'),
            name: $row->string('name'),
            email: new Email($row->string('email')),
            phone: $row->nullableString('phone'),
            passwordHash: $row->string('password'),
            role: $row->enum(UserRole::class, 'role'),
            passwordSetAt: $row->nullableDateTime('password_set_at'),
            emailVerifiedAt: $row->nullableDateTime('email_verified_at'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
            trash: new TrashState(
                status: $row->enum(TrashableStatus::class, 'status'),
                trashedAt: $row->nullableDateTime('deleted_at'),
                anonymizedAt: $row->nullableDateTime('anonymized_at'),
            ),
        );
    }

    /** @return array<string, mixed> */
    private function toParams(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email->value,
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
