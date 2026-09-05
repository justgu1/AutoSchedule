<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Postgres;

use App\Domain\Auth\PasswordResetToken;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;

final readonly class PostgresPasswordResetTokenRepository implements PasswordResetTokenRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function insert(PasswordResetToken $token): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at)
            VALUES (:id, :user_id, :token_hash, :expires_at)
            SQL);

        $statement->execute([
            'id' => $token->id,
            'user_id' => $token->userId,
            'token_hash' => $token->tokenHash,
            'expires_at' => $token->expiresAt->format(DATE_ATOM),
        ]);
    }

    public function findByRawToken(string $rawToken): ?PasswordResetToken
    {
        $statement = $this->pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => hash('sha256', $rawToken)]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function markUsed(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = now() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function invalidateAllForUser(string $userId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE password_reset_tokens SET used_at = now() WHERE user_id = :user_id AND used_at IS NULL
            SQL);

        $statement->execute(['user_id' => $userId]);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): PasswordResetToken
    {
        return new PasswordResetToken(
            id: $row['id'],
            userId: $row['user_id'],
            tokenHash: $row['token_hash'],
            expiresAt: new \DateTimeImmutable($row['expires_at']),
            usedAt: $row['used_at'] !== null ? new \DateTimeImmutable($row['used_at']) : null,
        );
    }
}
