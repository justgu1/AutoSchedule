<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\OAuthClient;

interface OAuthClientRepository
{
    /**
     * Clients are seeded, not registered at runtime yet -- read-only on purpose
     * (see the auth plan's "sem client dinâmico/admin UI" trade-off).
     */
    public function findByClientId(string $clientId): ?OAuthClient;
}
