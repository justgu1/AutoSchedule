<?php

declare(strict_types=1);

namespace App\Domain\Audit\Ports;

use App\Domain\Audit\AuditEvent;

interface AuditLogger
{
    /** @param array<string, mixed> $context detalhe extra (ex: email tentado num login falho) */
    public function record(AuditEvent $event, ?string $userId, array $context, string $ipAddress, ?string $userAgent): void;
}
