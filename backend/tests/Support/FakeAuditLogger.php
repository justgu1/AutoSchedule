<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;

/** Compartilhado porque antes cada suíte dependia de outro arquivo de teste ter carregado primeiro. */
final class FakeAuditLogger implements AuditLogger
{
    /** @var list<AuditEvent> */
    public array $events = [];

    /** @var list<AuditEntry> */
    public array $entries = [];

    public function record(AuditEntry $entry): void
    {
        $this->events[] = $entry->event;
        $this->entries[] = $entry;
    }
}
