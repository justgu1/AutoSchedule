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

/** Revoga todo refresh token junto: ninguém segue logado numa conta na lixeira. */
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
        // Marcada como cascata pra o restore devolver só o que caiu por causa da conta, não o que o dono já tinha arquivado.
        $this->dealerships->trashAllOwnedBy($user->id);
        $this->audit->record(AuditEvent::AccountTrashed, $context->actorId, 'User', $user->id, [], $context->ipAddress, $context->userAgent);
    }
}
