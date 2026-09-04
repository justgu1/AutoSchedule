<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Support\Uuid;

final class OAuthClient
{
    /**
     * @param list<GrantType> $allowedGrantTypes
     * @param list<string> $redirectUris
     * @param list<string> $allowedScopes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $clientId,
        public readonly string $name,
        public readonly ClientType $type,
        public readonly ?string $secretHash,
        public readonly array $allowedGrantTypes,
        public readonly array $redirectUris,
        public readonly array $allowedScopes,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<GrantType> $allowedGrantTypes
     * @param list<string> $redirectUris
     * @param list<string> $allowedScopes
     */
    public static function create(
        string $clientId,
        string $name,
        ClientType $type,
        array $allowedGrantTypes,
        array $redirectUris,
        array $allowedScopes,
        ?string $plainSecret = null,
    ): self {
        if ($type === ClientType::Confidential && $plainSecret === null) {
            throw new \InvalidArgumentException('Confidential clients require a secret.');
        }

        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            clientId: $clientId,
            name: $name,
            type: $type,
            // Client público não tem como guardar segredo com segurança (roda no
            // dispositivo do usuário final), então nunca tem um -- qualquer
            // $plainSecret informado pra ele é ignorado de propósito, não é
            // hasheado nem salvo.
            secretHash: $type === ClientType::Confidential ? password_hash($plainSecret, PASSWORD_ARGON2ID) : null,
            allowedGrantTypes: $allowedGrantTypes,
            redirectUris: $redirectUris,
            allowedScopes: $allowedScopes,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function supportsGrantType(GrantType $grantType): bool
    {
        return in_array($grantType, $this->allowedGrantTypes, true);
    }

    public function verifySecret(string $plainSecret): bool
    {
        return $this->secretHash !== null && password_verify($plainSecret, $this->secretHash);
    }
}
