<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;

final readonly class PostgresOAuthClientRepository implements OAuthClientRepository
{
    private const string COLUMNS = 'id, client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes, owner_user_id, revoked_at, created_at, updated_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findByClientId(string $clientId): ?OAuthClient
    {
        $statement = $this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM oauth_clients WHERE client_id = :client_id AND revoked_at IS NULL',
            ['client_id' => $clientId],
        );
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    public function findByIdForOwner(string $id, string $ownerUserId): ?OAuthClient
    {
        $statement = $this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM oauth_clients WHERE id = :id AND owner_user_id = :owner_user_id',
            ['id' => $id, 'owner_user_id' => $ownerUserId],
        );
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    public function findAllForOwner(string $ownerUserId): array
    {
        $statement = $this->connection->execute(
            'SELECT ' . self::COLUMNS . ' FROM oauth_clients WHERE owner_user_id = :owner_user_id ORDER BY created_at DESC',
            ['owner_user_id' => $ownerUserId],
        );

        return array_values(array_map(fn (mixed $row): OAuthClient => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    public function insert(OAuthClient $client): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO oauth_clients (
                id, client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes,
                owner_user_id, revoked_at, created_at, updated_at
            ) VALUES (
                :id, :client_id, :name, :type, :secret_hash, :allowed_grant_types, :redirect_uris, :allowed_scopes,
                :owner_user_id, :revoked_at, :created_at, :updated_at
            )
            SQL, $this->toParams($client));
    }

    public function update(OAuthClient $client): void
    {
        // client_id/type/owner_user_id nunca mudam depois de criado -- só secret, grants/escopo e revogação.
        $params = $this->toParams($client);
        unset($params['client_id'], $params['type'], $params['owner_user_id'], $params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE oauth_clients SET
                name = :name, secret_hash = :secret_hash, allowed_grant_types = :allowed_grant_types,
                redirect_uris = :redirect_uris, allowed_scopes = :allowed_scopes, revoked_at = :revoked_at,
                updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
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
            ownerUserId: $row->nullableString('owner_user_id'),
            revokedAt: $row->nullableDateTime('revoked_at'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|null> */
    private function toParams(OAuthClient $client): array
    {
        return [
            'id' => $client->id,
            'client_id' => $client->clientId,
            'name' => $client->name,
            'type' => $client->type->value,
            'secret_hash' => $client->secretHash,
            'allowed_grant_types' => PostgresArray::toText(array_map(static fn (GrantType $g): string => $g->value, $client->allowedGrantTypes)),
            'redirect_uris' => $client->redirectUris === [] ? null : PostgresArray::toText($client->redirectUris),
            'allowed_scopes' => PostgresArray::toText($client->allowedScopes),
            'owner_user_id' => $client->ownerUserId,
            'revoked_at' => $client->revokedAt?->format(DATE_ATOM),
            'created_at' => $client->createdAt->format(DATE_ATOM),
            'updated_at' => $client->updatedAt->format(DATE_ATOM),
        ];
    }
}
