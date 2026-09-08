<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

final readonly class UserFinder
{
    public function __construct(private UserRepository $users)
    {
    }

    public function findOrFail(string $userId): User
    {
        $user = $this->users->findById($userId);

        if (!$user instanceof User) {
            throw new DomainException('User not found.', DomainErrorType::NotFound);
        }

        return $user;
    }
}
