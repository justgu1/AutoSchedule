<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\User\UserRole;

/** Tudo opcional porque caso de uso não depende de `Request`: job não tem IP, rota pública não tem ator. */
final readonly class ActorContext
{
    public function __construct(
        public ?string $actorId = null,
        public ?UserRole $role = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
