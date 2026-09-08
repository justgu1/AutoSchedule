<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Transaction;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\RefreshToken;
use App\Domain\Auth\RefreshTokenAlreadyRotated;

final readonly class PostgresRefreshTokenRepository implements RefreshTokenRepository
{
    private const string COLUMNS = 'id, token_hash, family_id, client_id, user_id, scopes, expires_at, revoked_at, replaced_by_id';

    public function __construct(
        private DatabaseConnection $connection,
        private Transaction $transaction,
    ) {
    }

    public function insert(RefreshToken $token): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO oauth_refresh_tokens (id, token_hash, family_id, client_id, user_id, scopes, expires_at)
            VALUES (:id, :token_hash, :family_id, :client_id, :user_id, :scopes, :expires_at)
            SQL, [
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
        $statement = $this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM oauth_refresh_tokens WHERE token_hash = :token_hash',
            ['token_hash' => hash('sha256', $rawToken)],
        );
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    public function rotate(RefreshToken $current, RefreshToken $next): void
    {
        $this->transaction->run(fn (): null => $this->rotateInPlace($current, $next));
    }

    private function rotateInPlace(RefreshToken $current, RefreshToken $next): null
    {
        // A ordem é obrigatória: replaced_by_id tem FK pra própria tabela, e revogar condicionado a
        // revoked_at IS NULL é a trava de concorrência -- quem perder a corrida pega rowCount() = 0.
        $revoke = $this->connection->execute(
            'UPDATE oauth_refresh_tokens SET revoked_at = now() WHERE id = :current_id AND revoked_at IS NULL',
            ['current_id' => $current->id],
        );

        if ($revoke->rowCount() === 0) {
            throw new RefreshTokenAlreadyRotated();
        }

        $this->insert($next);

        $this->connection->execute(
            'UPDATE oauth_refresh_tokens SET replaced_by_id = :next_id WHERE id = :current_id',
            ['next_id' => $next->id, 'current_id' => $current->id],
        );

        return null;
    }

    public function revokeFamily(string $familyId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE oauth_refresh_tokens SET revoked_at = now() WHERE family_id = :family_id AND revoked_at IS NULL
            SQL, ['family_id' => $familyId]);
    }

    public function revokeAllForUser(string $userId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE oauth_refresh_tokens SET revoked_at = now() WHERE user_id = :user_id AND revoked_at IS NULL
            SQL, ['user_id' => $userId]);
    }

    private function fromRow(Row $row): RefreshToken
    {
        return new RefreshToken(
            id: $row->string('id'),
            tokenHash: $row->string('token_hash'),
            familyId: $row->string('family_id'),
            oauthClientId: $row->string('client_id'),
            userId: $row->nullableString('user_id'),
            scopes: $row->stringList('scopes'),
            expiresAt: $row->dateTime('expires_at'),
            revokedAt: $row->nullableDateTime('revoked_at'),
            replacedById: $row->nullableString('replaced_by_id'),
        );
    }
}
