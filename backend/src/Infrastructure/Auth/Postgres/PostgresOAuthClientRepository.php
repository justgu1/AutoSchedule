<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Postgres;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;

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
                self::parsePgTextArray($row['allowed_grant_types']),
            ),
            redirectUris: self::parsePgTextArray($row['redirect_uris']),
            allowedScopes: self::parsePgTextArray($row['allowed_scopes']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    /**
     * Parses a Postgres `text[]` value as returned by PDO_PGSQL, e.g. "{a,b,c}".
     * Only handles plain, comma-free elements (grant type slugs, scopes, URIs) --
     * not a general-purpose parser for quoted/escaped array literals.
     *
     * @return list<string>
     */
    private static function parsePgTextArray(?string $raw): array
    {
        if ($raw === null || $raw === '{}') {
            return [];
        }

        return explode(',', trim($raw, '{}'));
    }
}
