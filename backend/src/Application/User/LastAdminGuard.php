<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\UserRole;

/** Não cabe em `User` porque é invariante do conjunto: só responde quem sabe quantos admins existem. */
final readonly class LastAdminGuard
{
    public function __construct(private UserRepository $users)
    {
    }

    /** Barra o passo que deixaria o sistema sem nenhum admin. */
    public function assertNotLastAdmin(): void
    {
        if ($this->users->countByRole(UserRole::Admin) <= 1) {
            throw new DomainException('Cannot remove the last remaining admin.', DomainErrorType::Conflict);
        }
    }
}
