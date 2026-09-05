<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Support\Uuid;

/** Vincula uma conta a um provedor externo (hoje só Google) -- um usuário pode ter mais de uma identidade linkada. */
final class UserIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly string $email,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function link(string $userId, string $provider, string $providerUserId, string $email): self
    {
        return new self(
            id: Uuid::v7(),
            userId: $userId,
            provider: $provider,
            providerUserId: $providerUserId,
            email: $email,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
