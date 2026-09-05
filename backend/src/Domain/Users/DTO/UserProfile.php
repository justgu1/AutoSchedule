<?php

declare(strict_types=1);

namespace App\Domain\Users\DTO;

use App\Domain\Users\User;

final class UserProfile
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $role,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id, $user->name, $user->email, $user->phone, $user->role->value);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
        ];
    }
}
