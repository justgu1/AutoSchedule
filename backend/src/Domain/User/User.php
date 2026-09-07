<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\Uuid;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $passwordHash,
        public UserRole $role,
        public ?\DateTimeImmutable $passwordSetAt,
        public ?\DateTimeImmutable $emailVerifiedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $deletedAt,
        public TrashableStatus $status,
        public ?\DateTimeImmutable $anonymizedAt,
    ) {
    }

    public static function register(
        string $name,
        string $email,
        ?string $phone,
        string $plainPassword,
        UserRole $role,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
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
            status: TrashableStatus::Active,
            anonymizedAt: null,
        );
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function withProfile(string $name, ?string $phone): self
    {
        return clone($this, ['name' => $name, 'phone' => $phone, 'updatedAt' => new \DateTimeImmutable()]);
    }

    public function withNewPassword(string $plainPassword): self
    {
        $now = new \DateTimeImmutable();

        return clone($this, [
            'passwordHash' => password_hash($plainPassword, PASSWORD_ARGON2ID),
            'passwordSetAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    /** A única escalada que dispensa admin: customer virando seller por vontade própria. */
    public function isEligibleForSelfServiceRoleChange(UserRole $to): bool
    {
        return $this->role === UserRole::Customer && $to === UserRole::Seller;
    }

    public function withRole(UserRole $role): self
    {
        return clone($this, ['role' => $role, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /** Anonimização é irreversível, então ela é o que fecha a janela de restore. */
    public function isEligibleForRestore(): bool
    {
        return $this->status === TrashableStatus::Trashed && !$this->anonymizedAt instanceof \DateTimeImmutable;
    }

    /** Passou da janela de recuperação sem ser restaurado. */
    public function isEligibleForPurge(int $graceDays, \DateTimeImmutable $now): bool
    {
        if ($this->status !== TrashableStatus::Trashed || $this->anonymizedAt instanceof \DateTimeImmutable || !$this->deletedAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $this->deletedAt <= $now->modify("-{$graceDays} days");
    }

    /** Escruba PII (LGPD Art. 12) e preserva id/role/timestamps, senão a auditoria que referencia o usuário perde sentido. */
    public function anonymized(): self
    {
        return clone($this, [
            'name' => 'Deleted user',
            // Id inteiro, não prefixo: os primeiros hex de um UUIDv7 são timestamp e colidem entre exclusões próximas.
            'email' => sprintf('deleted-%s@anonymized.local', $this->id),
            'phone' => null,
            'status' => TrashableStatus::Deleted,
            'anonymizedAt' => new \DateTimeImmutable(),
        ]);
    }
}
