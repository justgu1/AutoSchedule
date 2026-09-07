<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Auth\UserIdentity;
use App\Domain\Shared\Email;

final readonly class PostgresUserIdentityRepository implements UserIdentityRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findByProvider(string $provider, string $providerUserId): ?UserIdentity
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT id, user_id, provider, provider_user_id, email, created_at FROM user_identities WHERE provider = :provider AND provider_user_id = :provider_user_id',
        );
        $statement->execute(['provider' => $provider, 'provider_user_id' => $providerUserId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    public function insert(UserIdentity $identity): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            INSERT INTO user_identities (id, user_id, provider, provider_user_id, email)
            VALUES (:id, :user_id, :provider, :provider_user_id, :email)
            SQL);

        $statement->execute([
            'id' => $identity->id,
            'user_id' => $identity->userId,
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email->value,
        ]);
    }

    private function fromRow(Row $row): UserIdentity
    {
        return new UserIdentity(
            id: $row->string('id'),
            userId: $row->string('user_id'),
            provider: $row->string('provider'),
            providerUserId: $row->string('provider_user_id'),
            email: new Email($row->string('email')),
            createdAt: $row->dateTime('created_at'),
        );
    }
}
