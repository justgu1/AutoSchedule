<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;

/** Troca autenticada: o Bearer prova quem é o usuário, não que ele ainda sabe a senha -- por isso exige a atual. */
final readonly class ChangeOwnPassword
{
    public function __construct(
        private UserFinder $finder,
        private UserRepository $users,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $userId, string $currentPassword, string $newPassword, ActorContext $context): void
    {
        $user = $this->finder->findOrFail($userId);

        if (!$user->verifyPassword($currentPassword)) {
            throw new DomainException('Current password is incorrect.', DomainErrorType::Unauthorized);
        }

        $this->users->update($user->withNewPassword($newPassword));
        $this->audit->record($context->audits(AuditEvent::PasswordChanged, $user->id, ['via' => 'self']));
    }
}
