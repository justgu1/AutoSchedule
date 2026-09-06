<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Ports\ScheduledTask;

/**
 * Rotina de purga genérica -- todo domínio com lixeira reversível (User,
 * Dealership, ...) monta uma instância desta classe em vez de escrever a
 * própria ScheduledTask. `findEligible`/`purge`/`identify` ficam a cargo de
 * cada domínio porque o próprio jeito de persistir a anonimização varia
 * (`User::anonymizeAndSoftDelete` vs `Dealership::anonymized()` + `update`).
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
            $this->audit->record($this->event, null, $this->auditableType, ($this->identify)($entity), [], '', null);
        }
    }
}
