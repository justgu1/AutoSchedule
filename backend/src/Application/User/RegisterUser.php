<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;

/** Serve cadastro público e admin: a diferença é a role que a rota aceita, não o que acontece aqui. */
final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $name, string $email, ?string $phone, string $password, UserRole $role, ActorContext $context): User
    {
        if ($this->users->existsByEmail($email)) {
            throw new DomainException('Email already in use.', DomainErrorType::Conflict, ['email' => 'Email already in use.']);
        }

        $user = User::register($name, $email, $phone, $password, $role);
        $this->users->insert($user);

        // Cadastro público não tem ator autenticado, então a conta criada é o próprio ator.
        $actorId = $context->actorId ?? $user->id;
        $this->audit->record(AuditEvent::UserCreated, $actorId, 'User', $user->id, ['role' => $user->role->value], $context->ipAddress, $context->userAgent);

        return $user;
    }
}
