<?php

declare(strict_types=1);

namespace App\Domain\Users;

use App\Domain\Support\Uuid;

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $passwordHash,
        public readonly UserRole $role,
        public readonly ?\DateTimeImmutable $passwordSetAt,
        public readonly ?\DateTimeImmutable $emailVerifiedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** Creates a brand-new user with a generated id, an Argon2id password hash and fresh timestamps. */
    public static function register(
        string $name,
        string $email,
        ?string $phone,
        string $plainPassword,
        UserRole $role,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v4(),
            name: $name,
            email: $email,
            phone: $phone,
            passwordHash: password_hash($plainPassword, PASSWORD_ARGON2ID),
            role: $role,
            passwordSetAt: $now,
            emailVerifiedAt: null,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
        );
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    /**
     * Returns a copy with PII scrubbed for LGPD "right to erasure" — id, password hash,
     * role and timestamps are kept so history/audit trails referencing this user stay valid.
     * Does not itself set deleted_at; the repository is responsible for the soft-delete.
     */
    public function anonymized(): self
    {
        return new self(
            id: $this->id,
            name: 'Deleted user',
            email: sprintf('deleted-%s@anonymized.local', substr($this->id, 0, 8)),
            phone: null,
            passwordHash: $this->passwordHash,
            role: $this->role,
            passwordSetAt: $this->passwordSetAt,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            deletedAt: $this->deletedAt,
        );
    }
}
