<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\UserIdentity;

interface UserIdentityRepository
{
    public function findByProvider(string $provider, string $providerUserId): ?UserIdentity;

    public function insert(UserIdentity $identity): void;
}
