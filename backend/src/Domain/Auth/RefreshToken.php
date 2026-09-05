<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Support\Uuid;

final readonly class RefreshToken
{
    /**
     * @param list<string> $scopes
     * @param ?string $userId ausente pra um futuro refresh token M2M (nenhum é emitido hoje)
     */
    public function __construct(
        public string $id,
        public string $tokenHash,
        public string $familyId,
        public string $oauthClientId,
        public ?string $userId,
        public array $scopes,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $revokedAt,
        public ?string $replacedById,
    ) {
    }

    /**
     * Primeiro token de uma família nova (uma por login). Só o hash é
     * persistido -- o token em texto puro é devolvido uma vez só, pra
     * mandar pro client.
     *
     * @param list<string> $scopes
     * @return array{0: string, 1: self} token em texto puro, entidade pra persistir
     */
    public static function issue(string $oauthClientId, ?string $userId, array $scopes, int $ttlSeconds): array
    {
        return self::mint($oauthClientId, $userId, $scopes, $ttlSeconds, Uuid::v7());
    }

    /**
     * Token rotacionado: mesma família de $this, usado pro repositório marcar
     * $this como revogado+substituído atomicamente junto de inserir a linha nova.
     *
     * @return array{0: string, 1: self} token em texto puro, entidade pra persistir
     */
    public function rotate(int $ttlSeconds): array
    {
        return self::mint($this->oauthClientId, $this->userId, $this->scopes, $ttlSeconds, $this->familyId);
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof \DateTimeImmutable;
    }

    /**
     * @param list<string> $scopes
     * @return array{0: string, 1: self}
     */
    private static function mint(string $oauthClientId, ?string $userId, array $scopes, int $ttlSeconds, string $familyId): array
    {
        $rawToken = bin2hex(random_bytes(32));

        $entity = new self(
            id: Uuid::v7(),
            tokenHash: hash('sha256', $rawToken),
            familyId: $familyId,
            oauthClientId: $oauthClientId,
            userId: $userId,
            scopes: $scopes,
            expiresAt: new \DateTimeImmutable()->modify("+{$ttlSeconds} seconds"),
            revokedAt: null,
            replacedById: null,
        );

        return [$rawToken, $entity];
    }
}
