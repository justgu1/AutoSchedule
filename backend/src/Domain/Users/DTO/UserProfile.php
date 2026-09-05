<?php

declare(strict_types=1);

namespace App\Domain\Users\DTO;

use App\Domain\Users\User;

final readonly class UserProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $role,
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
