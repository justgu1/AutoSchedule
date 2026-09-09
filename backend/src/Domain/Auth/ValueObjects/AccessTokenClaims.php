<?php

declare(strict_types=1);

namespace App\Domain\Auth\ValueObjects;

use App\Domain\Shared\Uuid;
use App\Domain\User\UserRole;

final readonly class AccessTokenClaims
{
    /**
     * @param list<string> $scopes
     * @param string $subject id do usuário -- inclusive em token M2M de client com dono, que empresta a identidade dele
     * @param ?UserRole $role ausente (null) só em token M2M de client sem dono (client de sistema, sem usuário por trás)
     */
    public function __construct(
        public string $subject,
        public string $clientId,
        public ?UserRole $role,
        public array $scopes,
        public string $jti,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    /** @param list<string> $scopes */
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
            expiresAt: new \DateTimeImmutable()->modify("+{$ttlSeconds} seconds"),
        );
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
