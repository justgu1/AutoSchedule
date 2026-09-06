<?php

declare(strict_types=1);

namespace App\Domain\Audit\Ports;

use App\Domain\Audit\AuditEvent;

interface AuditLogger
{
    /**
     * @param ?string $actorId quem executou a ação -- null quando a identidade não foi provada (login falho, reuso de refresh token)
     * @param string $auditableType nome da entidade afetada (ex: 'User', 'Dealership') -- polimórfico, cresce sem migration nova a cada domínio
     * @param ?string $auditableId id da entidade afetada -- igual a $actorId quando o próprio ator é o afetado, diferente quando alguém mexe no registro de outra pessoa/entidade
     * @param array<string, mixed> $context detalhe extra (ex: email tentado num login falho, quais campos mudaram)
     */
    public function record(AuditEvent $event, ?string $actorId, string $auditableType, ?string $auditableId, array $context, string $ipAddress, ?string $userAgent): void;
}
