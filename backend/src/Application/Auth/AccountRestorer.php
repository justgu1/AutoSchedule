<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

/**
 * Login com sucesso é a chance de recuperar a conta -- se ainda não foi
 * anonimizada em definitivo, sai da lixeira aqui, sem exigir passo extra do
 * usuário. Devolve se restaurou, pro token final avisar o frontend.
 *
 * Mora na camada Application porque compõe dois contextos: restaurar a conta
 * arrasta junto a concessionária que caiu na lixeira por causa dela.
 */
final readonly class AccountRestorer
{
    public function __construct(
        private UserRepository $users,
        private DealershipRepository $dealerships,
        private AuditLogger $audit,
    ) {
    }

    public function restoreIfTrashed(User $user, ActorContext $context): bool
    {
        if (!$user->isEligibleForRestore()) {
            return false;
        }

        $this->users->restore($user->id);
        $this->dealerships->restoreAutoTrashedOwnedBy($user->id);
        $this->audit->record(AuditEvent::AccountRestored, $user->id, 'User', $user->id, [], $context->ipAddress, $context->userAgent);

        return true;
    }
}
