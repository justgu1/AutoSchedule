<?php

declare(strict_types=1);

namespace App\Domain\Auth\ValueObjects;

use App\Domain\Support\Uuid;
use App\Domain\Users\UserRole;

final class AccessTokenClaims
{
    /**
     * @param list<string> $scopes
     * @param string $subject id do usuário num token WTM, client_id num token M2M
     * @param ?UserRole $role ausente (null) em token M2M -- não tem usuário
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $clientId,
        public readonly ?UserRole $role,
        public readonly array $scopes,
        public readonly string $jti,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Monta as claims de um access token novo: gera o jti e calcula o
     * expiresAt a partir do TTL informado.
     *
     * @param list<string> $scopes
     */
    public static function issue(
        string $subject,
        string $clientId,
        ?UserRole $role,
        array $scopes,
        int $ttlSeconds,
    ): self {
        return new self(
            subject: $subject,
            clientId: $clientId,
            role: $role,
            scopes: $scopes,
            jti: Uuid::v7(),
            expiresAt: (new \DateTimeImmutable())->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
