<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditEvent;
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

    /** @param array<string, mixed> $context */
    public function audits(AuditEvent $event, ?string $auditableId = null, array $context = []): AuditEntry
    {
        return new AuditEntry($event, $this->actorId, $auditableId, $context, $this->ipAddress, $this->userAgent);
    }

    /** Login prova a identidade no meio do fluxo: antes da senha conferir não há ator para registrar. */
    public function actedBy(string $actorId): self
    {
        return new self($actorId, $this->role, $this->ipAddress, $this->userAgent);
    }
}
