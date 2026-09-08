<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\PasswordResetToken;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;

final readonly class PostgresPasswordResetTokenRepository implements PasswordResetTokenRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function insert(PasswordResetToken $token): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at)
            VALUES (:id, :user_id, :token_hash, :expires_at)
            SQL, [
            'id' => $token->id,
            'user_id' => $token->userId,
            'token_hash' => $token->tokenHash,
            'expires_at' => $token->expiresAt->format(DATE_ATOM),
        ]);
    }

    public function findByRawToken(string $rawToken): ?PasswordResetToken
    {
        $statement = $this->connection->execute(
            'SELECT id, user_id, token_hash, expires_at, used_at FROM password_reset_tokens WHERE token_hash = :token_hash',
            ['token_hash' => hash('sha256', $rawToken)],
        );
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    public function markUsed(string $id): void
    {
        $this->connection->execute('UPDATE password_reset_tokens SET used_at = now() WHERE id = :id', ['id' => $id]);
    }

    public function invalidateAllForUser(string $userId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE password_reset_tokens SET used_at = now() WHERE user_id = :user_id AND used_at IS NULL
            SQL, ['user_id' => $userId]);
    }

    private function fromRow(Row $row): PasswordResetToken
    {
        return new PasswordResetToken(
            id: $row->string('id'),
            userId: $row->string('user_id'),
            tokenHash: $row->string('token_hash'),
            expiresAt: $row->dateTime('expires_at'),
            usedAt: $row->nullableDateTime('used_at'),
        );
    }
}
