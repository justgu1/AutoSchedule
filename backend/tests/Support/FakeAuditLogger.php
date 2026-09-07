<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;

/**
 * Dublê compartilhado: auditoria aparece em quase todo caso de uso, e antes
 * disto cada suíte dependia de outro arquivo de teste ter sido carregado
 * primeiro pra classe existir.
 */
final class FakeAuditLogger implements AuditLogger
{
    /** @var list<AuditEvent> */
    public array $events = [];

    /** @var list<array{event: AuditEvent, actorId: ?string, auditableType: string, targetUserId: ?string}> */
    public array $calls = [];

    /** @param array<string, mixed> $context */
    public function record(AuditEvent $event, ?string $actorId, string $auditableType, ?string $auditableId, array $context, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events[] = $event;
        $this->calls[] = ['event' => $event, 'actorId' => $actorId, 'auditableType' => $auditableType, 'targetUserId' => $auditableId];
    }
}
