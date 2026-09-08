<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\User\UserRole;

/**
 * Quem disparou a ação, com que autoridade e de onde. Tudo opcional porque
 * caso de uso não depende de `Request`: job no worker não tem IP nem user
 * agent, e rota pública não tem ator nenhum.
 */
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
