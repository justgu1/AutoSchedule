<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;

/** Admin-only -- recupera uma conta na lixeira sem esperar o dono logar de novo. */
final readonly class RestoreAccount
{
    public function __construct(
        private UserFinder $finder,
        private UserRepository $users,
        private DealershipRepository $dealerships,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $userId, ActorContext $context): void
    {
        $user = $this->finder->findOrFail($userId);

        if (!$user->isEligibleForRestore()) {
            throw new DomainException('This account is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->users->restore($user->id);
        $this->dealerships->restoreAutoTrashedOwnedBy($user->id);
        $this->audit->record(AuditEvent::AccountRestored, $context->actorId, 'User', $user->id, [], $context->ipAddress, $context->userAgent);
    }
}
