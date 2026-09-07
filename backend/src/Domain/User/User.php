<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Email;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashState;
use App\Domain\Shared\Uuid;

final readonly class User implements Trashable
{
    public function __construct(
        public string $id,
        public string $name,
        public Email $email,
        public ?string $phone,
        public string $passwordHash,
        public UserRole $role,
        public ?\DateTimeImmutable $passwordSetAt,
        public ?\DateTimeImmutable $emailVerifiedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public TrashState $trash,
    ) {
    }

    public static function register(
        string $name,
        Email $email,
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
            trash: new TrashState(),
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

    /** Escruba PII (LGPD Art. 12) e preserva id/role/timestamps, senão a auditoria que referencia o usuário perde sentido. */
    public function anonymized(): static
    {
        return clone($this, [
            'name' => 'Deleted user',
            // Id inteiro, não prefixo: os primeiros hex de um UUIDv7 são timestamp e colidem entre exclusões próximas.
            'email' => new Email(sprintf('deleted-%s@anonymized.local', $this->id)),
            'phone' => null,
            'trash' => $this->trash->anonymized(),
        ]);
    }
}
