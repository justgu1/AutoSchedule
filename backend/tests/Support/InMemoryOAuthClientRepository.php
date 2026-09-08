<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;

final class InMemoryOAuthClientRepository implements OAuthClientRepository
{
    /** @var array<string, OAuthClient> */
    private array $byId = [];

    public function __construct(OAuthClient ...$clients)
    {
        foreach ($clients as $client) {
            $this->byId[$client->id] = $client;
        }
    }

    public function findByClientId(string $clientId): ?OAuthClient
    {
        foreach ($this->byId as $client) {
            if ($client->clientId === $clientId && !$client->isRevoked()) {
                return $client;
            }
        }

        return null;
    }

    public function findByIdForOwner(string $id, string $ownerUserId): ?OAuthClient
    {
        $client = $this->byId[$id] ?? null;

        return $client instanceof OAuthClient && $client->isOwnedBy($ownerUserId) ? $client : null;
    }

    public function findAllForOwner(string $ownerUserId): array
    {
        return array_values(array_filter($this->byId, static fn (OAuthClient $c): bool => $c->isOwnedBy($ownerUserId)));
    }

    public function insert(OAuthClient $client): void
    {
        $this->byId[$client->id] = $client;
    }

    public function update(OAuthClient $client): void
    {
        $this->byId[$client->id] = $client;
    }
}
