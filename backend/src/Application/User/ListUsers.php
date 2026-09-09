<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\User\DTO\UserProfile;
use App\Domain\User\Ports\UserRepository;

final readonly class ListUsers
{
    public function __construct(private UserRepository $users)
    {
    }

    /** @return array{items: list<UserProfile>, total: int} */
    public function __invoke(int $limit, int $offset, ?string $role = null): array
    {
        return [
            'items' => array_map(
                UserProfile::fromUser(...),
                $this->users->findPage($limit, $offset, $role),
            ),
            'total' => $this->users->count($role),
        ];
    }
}
