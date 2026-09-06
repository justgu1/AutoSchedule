<?php

declare(strict_types=1);

namespace App\Infrastructure\Users\Scheduler;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Ports\ScheduledTask;
use App\Domain\Users\Ports\UserRepository;

final readonly class PurgeTrashedUsersTask implements ScheduledTask
{
    private const int GRACE_DAYS = 30;

    public function __construct(
        private UserRepository $users,
        private AuditLogger $audit,
    ) {
    }

    public function name(): string
    {
        return 'purge-trashed-users';
    }

    public function dueIntervalSeconds(): int
    {
        return 86400;
    }

    public function run(): void
    {
        foreach ($this->users->findPurgeEligible(self::GRACE_DAYS, new \DateTimeImmutable()) as $user) {
            $this->users->anonymizeAndSoftDelete($user->id);
            $this->audit->record(AuditEvent::AccountPurged, null, $user->id, [], '', null);
        }
    }
}
