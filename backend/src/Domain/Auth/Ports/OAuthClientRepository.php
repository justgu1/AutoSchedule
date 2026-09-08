<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\OAuthClient;

interface OAuthClientRepository
{
    /** Nunca devolve um client revogado -- revogar precisa negar login imediatamente, não só ao expirar o token. */
    public function findByClientId(string $clientId): ?OAuthClient;

    public function findByIdForOwner(string $id, string $ownerUserId): ?OAuthClient;

    /** @return list<OAuthClient> */
    public function findAllForOwner(string $ownerUserId): array;

    public function insert(OAuthClient $client): void;

    public function update(OAuthClient $client): void;
}
