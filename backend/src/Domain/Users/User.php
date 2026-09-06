<?php

declare(strict_types=1);

namespace App\Domain\Users;

use App\Domain\Shared\TrashableStatus;
use App\Domain\Support\Uuid;

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

    /** Devolve uma cópia com nome/telefone atualizados -- usado pelo self-service (PATCH /me). */
    public function withProfile(string $name, ?string $phone): self
    {
        return new self(
            id: $this->id,
            name: $name,
            email: $this->email,
            phone: $phone,
            passwordHash: $this->passwordHash,
            role: $this->role,
            passwordSetAt: $this->passwordSetAt,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
            deletedAt: $this->deletedAt,
            status: $this->status,
            anonymizedAt: $this->anonymizedAt,
        );
    }

    /** Devolve uma cópia com uma nova senha (hash Argon2id) e passwordSetAt renovado. */
    public function withNewPassword(string $plainPassword): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            phone: $this->phone,
            passwordHash: password_hash($plainPassword, PASSWORD_ARGON2ID),
            role: $this->role,
            passwordSetAt: $now,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: $now,
            deletedAt: $this->deletedAt,
            status: $this->status,
            anonymizedAt: $this->anonymizedAt,
        );
    }

    /**
     * Self-service só permite uma escalada: `customer` virando `seller`, por
     * vontade própria (ex: login social provisiona `customer`, mas a pessoa
     * quer vender). Nunca vira `admin`, nunca desce de role, nunca a partir de
     * `seller`/`admin` -- qualquer outra transição passa só pelo CRUD admin.
     */
    public function isEligibleForSelfServiceRoleChange(UserRole $to): bool
    {
        return $this->role === UserRole::Customer && $to === UserRole::Seller;
    }

    /** Devolve uma cópia com role trocado -- usado pelo CRUD admin (/users) e pela escalada self-service restrita acima. */
    public function withRole(UserRole $role): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            phone: $this->phone,
            passwordHash: $this->passwordHash,
            role: $role,
            passwordSetAt: $this->passwordSetAt,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
            deletedAt: $this->deletedAt,
            status: $this->status,
            anonymizedAt: $this->anonymizedAt,
        );
    }

    /** Só dá pra restaurar da lixeira antes da anonimização definitiva ter rodado. */
    public function isEligibleForRestore(): bool
    {
        return $this->status === TrashableStatus::Trashed && !$this->anonymizedAt instanceof \DateTimeImmutable;
    }

    /** Passou da janela de recuperação (30 dias) sem ser restaurado -- elegível pra rotina de purge. */
    public function isEligibleForPurge(int $graceDays, \DateTimeImmutable $now): bool
    {
        if ($this->status !== TrashableStatus::Trashed || $this->anonymizedAt instanceof \DateTimeImmutable || !$this->deletedAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $this->deletedAt <= $now->modify("-{$graceDays} days");
    }

    /**
     * Devolve uma cópia com PII escrubada pro "direito ao esquecimento" da LGPD —
     * id, hash de senha, role e timestamps são mantidos pra histórico/auditoria
     * que referencia esse usuário continuar válido. Não seta deleted_at por
     * conta própria; quem faz o soft-delete é o repositório.
     */
    public function anonymized(): self
    {
        return new self(
            id: $this->id,
            name: 'Deleted user',
            // Id inteiro, não um prefixo -- os primeiros 8 hex de um UUIDv7 só
            // mudam a cada ~65s (são os bits mais altos do timestamp em ms), então
            // um prefixo curto colide fácil entre exclusões próximas no tempo.
            email: sprintf('deleted-%s@anonymized.local', $this->id),
            phone: null,
            passwordHash: $this->passwordHash,
            role: $this->role,
            passwordSetAt: $this->passwordSetAt,
            emailVerifiedAt: $this->emailVerifiedAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            deletedAt: $this->deletedAt,
            status: TrashableStatus::Deleted,
            anonymizedAt: new \DateTimeImmutable(),
        );
    }
}
