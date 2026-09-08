<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

/**
 * Login com sucesso é a chance de recuperar a conta, sem exigir passo extra do usuário.
 * Mora na Application porque compõe dois contextos: restaurar a conta arrasta a concessionária.
 */
final readonly class AccountRestorer
{
    public function __construct(
        private UserRepository $users,
        private DealershipRepository $dealerships,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    public function restoreIfTrashed(User $user, ActorContext $context): bool
    {
        if (!$user->trash->allowsRestore()) {
            return false;
        }

        $this->transaction->run(function () use ($user): void {
            $this->users->restore($user->id);
            $this->dealerships->restoreAutoTrashedOwnedBy($user->id);
        });

        $this->audit->record($context->actedBy($user->id)->audits(AuditEvent::AccountRestored, $user->id));

        return true;
    }
}
