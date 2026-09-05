<?php

declare(strict_types=1);

namespace App\Domain\Audit\Ports;

use App\Domain\Audit\AuditEvent;

interface AuditLogger
{
    /**
     * @param ?string $actorId quem executou a ação -- null quando a identidade não foi provada (login falho, reuso de refresh token)
     * @param ?string $targetUserId conta afetada pela ação -- igual a $actorId numa ação sobre a própria conta, diferente quando um admin mexe em outro usuário
     * @param array<string, mixed> $context detalhe extra (ex: email tentado num login falho, quais campos mudaram)
     */
    public function record(AuditEvent $event, ?string $actorId, ?string $targetUserId, array $context, string $ipAddress, ?string $userAgent): void;
}
