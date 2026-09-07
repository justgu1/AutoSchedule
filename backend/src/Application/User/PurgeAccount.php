<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;

/** Antecipa o que a purga agendada faria, a pedido do dono ou de um admin. */
final readonly class PurgeAccount
{
    public function __construct(
        private UserFinder $finder,
        private UserRepository $users,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $userId, ActorContext $context): void
    {
        $user = $this->finder->findOrFail($userId);

        if (!$user->trash->isTrashed()) {
            throw new DomainException('This account is not in the trash.', DomainErrorType::Conflict);
        }

        $this->users->purge($user->anonymized());
        $this->audit->record(AuditEvent::AccountPurged, $context->actorId, 'User', $user->id, [], $context->ipAddress, $context->userAgent);
    }
}
