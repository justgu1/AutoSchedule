<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;

/**
 * Uma instância por domínio com lixeira reversível, em vez de uma ScheduledTask por domínio.
 * As closures existem porque cada domínio persiste a anonimização de um jeito diferente.
 *
 * @template T of object
 */
final readonly class PurgeTrashedEntitiesTask implements ScheduledTask
{
    /**
     * @param \Closure(int, \DateTimeImmutable): list<T> $findEligible retorna as entidades elegíveis pra purga
     * @param \Closure(T): void $purge persiste a anonimização de uma entidade
     * @param \Closure(T): string $identify extrai o id da entidade, só pra auditoria
     */
    public function __construct(
        private string $name,
        private int $graceDays,
        private int $dueIntervalSeconds,
        private \Closure $findEligible,
        private \Closure $purge,
        private \Closure $identify,
        private AuditLogger $audit,
        private AuditEvent $event,
        private string $auditableType,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function dueIntervalSeconds(): int
    {
        return $this->dueIntervalSeconds;
    }

    public function run(): void
    {
        foreach (($this->findEligible)($this->graceDays, new \DateTimeImmutable()) as $entity) {
            ($this->purge)($entity);
            $this->audit->record($this->event, null, $this->auditableType, ($this->identify)($entity), [], null, null);
        }
    }
}
