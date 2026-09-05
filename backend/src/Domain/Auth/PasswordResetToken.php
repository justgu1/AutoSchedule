<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Support\Uuid;

final readonly class PasswordResetToken
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $tokenHash,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $usedAt,
    ) {
    }

    /**
     * Só o hash é persistido -- o token em texto puro é devolvido uma vez só,
     * pra ir no link do e-mail.
     *
     * @return array{0: string, 1: self} token em texto puro, entidade pra persistir
     */
    public static function issue(string $userId, int $ttlSeconds): array
    {
        $rawToken = bin2hex(random_bytes(32));

        $entity = new self(
            id: Uuid::v7(),
            userId: $userId,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: new \DateTimeImmutable()->modify("+{$ttlSeconds} seconds"),
            usedAt: null,
        );

        return [$rawToken, $entity];
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->usedAt instanceof \DateTimeImmutable;
    }
}
