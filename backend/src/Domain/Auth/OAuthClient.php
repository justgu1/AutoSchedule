<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Shared\Uuid;

final readonly class OAuthClient
{
    /**
     * @param list<GrantType> $allowedGrantTypes
     * @param list<string> $redirectUris
     * @param list<string> $allowedScopes
     */
    public function __construct(
        public string $id,
        public string $clientId,
        public string $name,
        public ClientType $type,
        public ?string $secretHash,
        public array $allowedGrantTypes,
        public array $redirectUris,
        public array $allowedScopes,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
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
            // Client público roda no dispositivo do usuário final, então não guarda segredo: $plainSecret é ignorado de propósito.
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
