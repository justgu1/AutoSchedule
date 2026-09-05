<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Postgres;

use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\RefreshToken;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Database\PostgresArray;

final readonly class PostgresRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function insert(RefreshToken $token): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO oauth_refresh_tokens (id, token_hash, family_id, client_id, user_id, scopes, expires_at)
            VALUES (:id, :token_hash, :family_id, :client_id, :user_id, :scopes, :expires_at)
            SQL);

        $statement->execute([
            'id' => $token->id,
            'token_hash' => $token->tokenHash,
            'family_id' => $token->familyId,
            'client_id' => $token->oauthClientId,
            'user_id' => $token->userId,
            'scopes' => PostgresArray::toText($token->scopes),
            'expires_at' => $token->expiresAt->format(DATE_ATOM),
        ]);
    }

    public function findByRawToken(string $rawToken): ?RefreshToken
    {
        $statement = $this->pdo->prepare('SELECT * FROM oauth_refresh_tokens WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => hash('sha256', $rawToken)]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function rotate(RefreshToken $current, RefreshToken $next): void
    {
        // Três passos, nessa ordem, porque replaced_by_id tem FK pra essa mesma
        // tabela -- não pode apontar pro $next antes dele existir. Revogar
        // $current primeiro (condicionado a revoked_at IS NULL) também é a
        // trava de concorrência: duas renovações concorrentes no mesmo token
        // não conseguem "ganhar" esse UPDATE ao mesmo tempo, quem perder pega
        // rowCount() = 0 e nunca chega no insert abaixo -- $next nunca é
        // duplicado pra um token só.
        $revoke = $this->pdo->prepare(
            'UPDATE oauth_refresh_tokens SET revoked_at = now() WHERE id = :current_id AND revoked_at IS NULL',
        );
        $revoke->execute(['current_id' => $current->id]);

        if ($revoke->rowCount() === 0) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        $this->insert($next);

        $link = $this->pdo->prepare(
            'UPDATE oauth_refresh_tokens SET replaced_by_id = :next_id WHERE id = :current_id',
        );
        $link->execute(['next_id' => $next->id, 'current_id' => $current->id]);
    }

    public function revokeFamily(string $familyId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE oauth_refresh_tokens SET revoked_at = now() WHERE family_id = :family_id AND revoked_at IS NULL
            SQL);

        $statement->execute(['family_id' => $familyId]);
    }

    public function revokeAllForUser(string $userId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE oauth_refresh_tokens SET revoked_at = now() WHERE user_id = :user_id AND revoked_at IS NULL
            SQL);

        $statement->execute(['user_id' => $userId]);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): RefreshToken
    {
        return new RefreshToken(
            id: $row['id'],
            tokenHash: $row['token_hash'],
            familyId: $row['family_id'],
            oauthClientId: $row['client_id'],
            userId: $row['user_id'],
            scopes: PostgresArray::fromText($row['scopes']),
            expiresAt: new \DateTimeImmutable($row['expires_at']),
            revokedAt: $row['revoked_at'] !== null ? new \DateTimeImmutable($row['revoked_at']) : null,
            replacedById: $row['replaced_by_id'],
        );
    }
}
