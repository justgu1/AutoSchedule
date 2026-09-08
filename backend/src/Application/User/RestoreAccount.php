<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;

/** Recupera sem esperar o dono logar de novo, que é o outro caminho de restore. */
final readonly class RestoreAccount
{
    public function __construct(
        private UserFinder $finder,
        private UserRepository $users,
        private DealershipRepository $dealerships,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(string $userId, ActorContext $context): void
    {
        $user = $this->finder->findOrFail($userId);

        if (!$user->trash->allowsRestore()) {
            throw new DomainException('This account is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->transaction->run(function () use ($user): void {
            $this->users->restore($user->id);
            $this->dealerships->restoreAutoTrashedOwnedBy($user->id);
        });

        $this->audit->record($context->audits(AuditEvent::AccountRestored, $user->id));
    }
}
