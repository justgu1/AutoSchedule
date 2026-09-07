<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;

final readonly class PostgresOAuthClientRepository implements OAuthClientRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findByClientId(string $clientId): ?OAuthClient
    {
        $statement = $this->connection->execute(
            'SELECT id, client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes, created_at, updated_at FROM oauth_clients WHERE client_id = :client_id',
            ['client_id' => $clientId],
        );
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    private function fromRow(Row $row): OAuthClient
    {
        return new OAuthClient(
            id: $row->string('id'),
            clientId: $row->string('client_id'),
            name: $row->string('name'),
            type: $row->enum(ClientType::class, 'type'),
            secretHash: $row->nullableString('secret_hash'),
            allowedGrantTypes: $row->enumList(GrantType::class, 'allowed_grant_types'),
            redirectUris: $row->stringList('redirect_uris'),
            allowedScopes: $row->stringList('allowed_scopes'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }
}
