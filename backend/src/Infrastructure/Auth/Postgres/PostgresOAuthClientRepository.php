<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Postgres;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Infrastructure\Database\PostgresArray;

final class PostgresOAuthClientRepository implements OAuthClientRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findByClientId(string $clientId): ?OAuthClient
    {
        $statement = $this->pdo->prepare('SELECT * FROM oauth_clients WHERE client_id = :client_id');
        $statement->execute(['client_id' => $clientId]);
        $row = $statement->fetch();

        return $row === false ? null : self::fromRow($row);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): OAuthClient
    {
        return new OAuthClient(
            id: $row['id'],
            clientId: $row['client_id'],
            name: $row['name'],
            type: ClientType::from($row['type']),
            secretHash: $row['secret_hash'],
            allowedGrantTypes: array_map(
                static fn (string $value): GrantType => GrantType::from($value),
                PostgresArray::fromText($row['allowed_grant_types']),
            ),
            redirectUris: PostgresArray::fromText($row['redirect_uris']),
            allowedScopes: PostgresArray::fromText($row['allowed_scopes']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
