<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;

/**
 * Self troca name/phone e, no máximo, escala a própria role de `customer`
 * pra `seller` (`User::isEligibleForSelfServiceRoleChange`) -- qualquer
 * outra transição no caminho self é rejeitada. Admin mexendo em outro id
 * troca pra qualquer role (com a trava do último admin).
 */
final readonly class UpdateUserProfile
{
    public function __construct(
        private UserFinder $finder,
        private UserRepository $users,
        private LastAdminGuard $lastAdminGuard,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $userId, ValidatedInput $changes, bool $managingAnotherUser, ActorContext $context): User
    {
        $user = $this->finder->findOrFail($userId);
        $previousRole = $user->role;
        $user = $user->withProfile($changes->stringOr('name', $user->name), $changes->stringOrNull('phone') ?? $user->phone);

        if ($changes->has('role')) {
            $user = $user->withRole($this->resolveNewRole($user, UserRole::from($changes->string('role')), $previousRole, $managingAnotherUser));
        }

        $this->users->update($user);
        // Só o nome dos campos de perfil alterados (sem valor -- não duplica PII),
        // mas role muda quem pode fazer o quê no sistema, então guarda de/para inteiro.
        $auditContext = ['fields' => $changes->fields()];

        if ($user->role !== $previousRole) {
            $auditContext['role'] = ['from' => $previousRole->value, 'to' => $user->role->value];
        }

        $this->audit->record(AuditEvent::ProfileUpdated, $context->actorId, 'User', $user->id, $auditContext, $context->ipAddress, $context->userAgent);

        return $user;
    }

    private function resolveNewRole(User $user, UserRole $newRole, UserRole $previousRole, bool $managingAnotherUser): UserRole
    {
        if ($managingAnotherUser) {
            if ($previousRole === UserRole::Admin && $newRole !== UserRole::Admin) {
                $this->lastAdminGuard->assertNotLastAdmin();
            }

            return $newRole;
        }

        if (!$user->isEligibleForSelfServiceRoleChange($newRole)) {
            throw new DomainException('Only customer accounts can self-upgrade to seller.', DomainErrorType::Forbidden);
        }

        return $newRole;
    }
}
