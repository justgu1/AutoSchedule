<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\UserRole;

/**
 * Move pra lixeira (reversível por 30 dias -- login de novo restaura, ou
 * `RestoreAccount`/`PurgeAccount`) e revoga todo refresh token, ninguém
 * continua logado depois disso.
 */
final readonly class TrashAccount
{
    public function __construct(
        private UserFinder $finder,
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private DealershipRepository $dealerships,
        private LastAdminGuard $lastAdminGuard,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $userId, ActorContext $context): void
    {
        $user = $this->finder->findOrFail($userId);

        if ($user->role === UserRole::Admin) {
            $this->lastAdminGuard->assertNotLastAdmin();
        }

        $this->users->trash($user->id);
        $this->refreshTokens->revokeAllForUser($user->id);
        // Cascata: concessionária ativa desse seller vai junto pra lixeira (marcada como "por causa da desativação",
        // pra restaurar seletivo depois -- a que ele já tinha trashed manualmente antes fica quieta).
        $this->dealerships->trashAllOwnedBy($user->id);
        $this->audit->record(AuditEvent::AccountTrashed, $context->actorId, 'User', $user->id, [], $context->ipAddress, $context->userAgent);
    }
}
