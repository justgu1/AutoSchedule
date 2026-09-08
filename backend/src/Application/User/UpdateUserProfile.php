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

/** Dois caminhos com poder diferente: admin troca qualquer role, self só a escalada que `User` permite. */
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
        // Só o nome dos campos, pra não duplicar PII na auditoria; role é exceção porque muda permissão.
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
