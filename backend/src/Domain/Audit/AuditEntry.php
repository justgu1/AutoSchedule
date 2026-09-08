<?php

declare(strict_types=1);

namespace App\Domain\Audit;

/** Os sete parâmetros de `record()`, quatro deles `?string` intercambiáveis, viram campos com nome. */
final readonly class AuditEntry
{
    /**
     * @param ?string $actorId quem executou -- null quando a identidade não foi provada (login falho, reuso de refresh token)
     * @param ?string $auditableId entidade afetada -- igual ao ator quando alguém mexe no próprio registro
     * @param array<string, mixed> $context detalhe extra (e-mail tentado num login falho, campos alterados)
     * @param ?string $ipAddress null fora de um request HTTP (job de fila, rotina agendada)
     */
    public function __construct(
        public AuditEvent $event,
        public ?string $actorId = null,
        public ?string $auditableId = null,
        public array $context = [],
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }

    public function auditableType(): AuditableType
    {
        return $this->event->auditableType();
    }
}
