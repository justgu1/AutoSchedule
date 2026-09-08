<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Application\Ports\Transaction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Shared\Ports\TrashableRepository;

/** Uma instância por domínio com lixeira reversível, em vez de uma ScheduledTask por domínio. */
final readonly class PurgeTrashedEntitiesTask implements ScheduledTask
{
    public function __construct(
        private string $name,
        private int $dueIntervalSeconds,
        private TrashableRepository $repository,
        private AuditLogger $audit,
        private AuditEvent $event,
        private Transaction $transaction,
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
        $now = new \DateTimeImmutable();

        foreach ($this->repository->findTrashed() as $entity) {
            if (!$entity->trash->allowsPurge($now)) {
                continue;
            }

            // Uma transação por entidade: uma linha problemática não desfaz as que já foram anonimizadas.
            $this->transaction->run(fn (): null => $this->repository->purge($entity->anonymized()));
            $this->audit->record(new AuditEntry($this->event, auditableId: $entity->id));
        }
    }
}
