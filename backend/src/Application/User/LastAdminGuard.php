<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\UserRole;

/**
 * Invariante sobre o conjunto de usuários, não sobre um usuário -- por isso
 * não cabe em `User`: só dá pra responder consultando quantos admins existem.
 */
final readonly class LastAdminGuard
{
    public function __construct(private UserRepository $users)
    {
    }

    /** Chamado só quando o usuário já é admin -- barra o passo que o tiraria do papel (delete ou troca de role) se ele for o único. */
    public function assertNotLastAdmin(): void
    {
        if ($this->users->countByRole(UserRole::Admin) <= 1) {
            throw new DomainException('Cannot remove the last remaining admin.', DomainErrorType::Conflict);
        }
    }
}
