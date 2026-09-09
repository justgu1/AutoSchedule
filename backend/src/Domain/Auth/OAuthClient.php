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
        public ?string $ownerUserId,
        public ?\DateTimeImmutable $revokedAt,
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
            ownerUserId: null,
            revokedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * Client m2m self-service: sempre confidencial, sempre `client_credentials`, sem escopo
     * próprio -- em runtime ele herda a permissão do dono (ver `IssueServiceToken`).
     *
     * @return array{0: string, 1: self} secret em texto puro, entidade pra persistir
     */
    public static function createForOwner(string $ownerUserId, string $name): array
    {
        $rawSecret = bin2hex(random_bytes(24));

        $client = self::create(
            clientId: 'usr_' . Uuid::v7(),
            name: $name,
            type: ClientType::Confidential,
            allowedGrantTypes: [GrantType::ClientCredentials],
            redirectUris: [],
            allowedScopes: [],
            plainSecret: $rawSecret,
        );

        return [$rawSecret, clone($client, ['ownerUserId' => $ownerUserId])];
    }

    public function supportsGrantType(GrantType $grantType): bool
    {
        return in_array($grantType, $this->allowedGrantTypes, true);
    }

    public function verifySecret(string $plainSecret): bool
    {
        return $this->secretHash !== null && password_verify($plainSecret, $this->secretHash);
    }

    public function isOwnedBy(string $userId): bool
    {
        return $this->ownerUserId === $userId;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof \DateTimeImmutable;
    }

    /** @return array{0: string, 1: self} novo secret em texto puro, entidade pra persistir */
    #[\NoDiscard]
    public function rotateSecret(): array
    {
        $rawSecret = bin2hex(random_bytes(24));

        return [$rawSecret, clone($this, [
            'secretHash' => password_hash($rawSecret, PASSWORD_ARGON2ID),
            'updatedAt' => new \DateTimeImmutable(),
        ])];
    }

    #[\NoDiscard]
    public function revoked(): self
    {
        return clone($this, ['revokedAt' => new \DateTimeImmutable(), 'updatedAt' => new \DateTimeImmutable()]);
    }
}
