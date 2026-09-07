<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Lixeira reversível compartilhada por todo domínio que tem uma: as regras existiam duplicadas
 * em `User` e `Dealership`, e a janela de recuperação vivia em seis lugares.
 */
final readonly class TrashState
{
    /** Janela de recuperação da LGPD. Único lugar onde esse prazo existe. */
    public const int GRACE_DAYS = 30;

    public function __construct(
        public TrashableStatus $status = TrashableStatus::Active,
        public ?\DateTimeImmutable $trashedAt = null,
        public ?\DateTimeImmutable $anonymizedAt = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === TrashableStatus::Active;
    }

    public function isTrashed(): bool
    {
        return $this->status === TrashableStatus::Trashed;
    }

    /** Anonimização é irreversível, então ela é o que fecha a janela de restore. */
    public function allowsRestore(): bool
    {
        return $this->isTrashed() && !$this->anonymizedAt instanceof \DateTimeImmutable;
    }

    /** Passou da janela de recuperação sem ser restaurado. */
    public function allowsPurge(\DateTimeImmutable $now): bool
    {
        if (!$this->allowsRestore() || !$this->trashedAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $this->trashedAt <= $now->modify('-' . self::GRACE_DAYS . ' days');
    }

    #[\NoDiscard]
    public function trashed(?\DateTimeImmutable $at = null): self
    {
        return new self(TrashableStatus::Trashed, $at ?? new \DateTimeImmutable(), $this->anonymizedAt);
    }

    #[\NoDiscard]
    public function restored(): self
    {
        return new self(TrashableStatus::Active, null, $this->anonymizedAt);
    }

    #[\NoDiscard]
    public function anonymized(): self
    {
        return new self(TrashableStatus::Deleted, $this->trashedAt, new \DateTimeImmutable());
    }
}
