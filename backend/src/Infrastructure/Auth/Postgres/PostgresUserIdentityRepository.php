<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Postgres;

use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Auth\UserIdentity;

final class PostgresUserIdentityRepository implements UserIdentityRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findByProvider(string $provider, string $providerUserId): ?UserIdentity
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM user_identities WHERE provider = :provider AND provider_user_id = :provider_user_id',
        );
        $statement->execute(['provider' => $provider, 'provider_user_id' => $providerUserId]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    public function insert(UserIdentity $identity): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO user_identities (id, user_id, provider, provider_user_id, email)
            VALUES (:id, :user_id, :provider, :provider_user_id, :email)
            SQL);

        $statement->execute([
            'id' => $identity->id,
            'user_id' => $identity->userId,
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email,
        ]);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): UserIdentity
    {
        return new UserIdentity(
            id: $row['id'],
            userId: $row['user_id'],
            provider: $row['provider'],
            providerUserId: $row['provider_user_id'],
            email: $row['email'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }
}
