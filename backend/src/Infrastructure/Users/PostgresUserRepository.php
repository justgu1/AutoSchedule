<?php

declare(strict_types=1);

namespace App\Infrastructure\Users;

use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Domain\Users\UserStatus;

final readonly class PostgresUserRepository implements UserRepository
{
    public function __construct(private \PDO $pdo)
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
        $statement = $this->pdo->prepare("SELECT 1 FROM users WHERE email = :email AND status <> 'deleted'");
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function insert(User $user): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (id, name, email, phone, password, role, password_set_at, email_verified_at, created_at, updated_at)
            VALUES (:id, :name, :email, :phone, :password, :role, :password_set_at, :email_verified_at, :created_at, :updated_at)
            SQL);

        $statement->execute($this->toParams($user));
    }

    public function update(User $user): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
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
        $statement = $this->pdo->prepare(
            "UPDATE users SET status = 'trashed', deleted_at = now(), updated_at = now() WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE users SET status = 'active', deleted_at = NULL, updated_at = now() WHERE id = :id",
        );
        $statement->execute(['id' => $id]);
    }

    public function anonymizeAndSoftDelete(string $id): void
    {
        $user = $this->findById($id);

        if (!$user instanceof User) {
            return;
        }

        $anonymized = $user->anonymized();

        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE users SET
                name = :name, email = :email, phone = :phone,
                status = 'deleted', anonymized_at = now(), deleted_at = now(), updated_at = now()
            WHERE id = :id
            SQL);

        $statement->execute([
            'id' => $id,
            'name' => $anonymized->name,
            'email' => $anonymized->email,
            'phone' => $anonymized->phone,
        ]);
    }

    public function findPurgeEligible(int $graceDays, \DateTimeImmutable $now): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT * FROM users
            WHERE status = 'trashed' AND anonymized_at IS NULL AND deleted_at <= :threshold
            SQL);
        $statement->execute(['threshold' => $now->modify("-{$graceDays} days")->format(DATE_ATOM)]);

        return array_map($this->fromRow(...), $statement->fetchAll());
    }

    public function findPage(int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM users WHERE status <> 'deleted' ORDER BY created_at LIMIT :limit OFFSET :offset",
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->fromRow(...), $statement->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE status <> 'deleted'")->fetchColumn();
    }

    public function countByRole(UserRole $role): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND status = 'active'");
        $statement->execute(['role' => $role->value]);

        return (int) $statement->fetchColumn();
    }

    private function findOneBy(string $column, string $value): ?User
    {
        $statement = $this->pdo->prepare("SELECT * FROM users WHERE {$column} = :value AND status <> 'deleted'");
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
            deletedAt: $this->toDateTime($row['deleted_at']),
            status: UserStatus::from($row['status']),
            anonymizedAt: $this->toDateTime($row['anonymized_at']),
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
