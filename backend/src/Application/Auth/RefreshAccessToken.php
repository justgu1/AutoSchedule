<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\DTO\TokenPair;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\RefreshToken;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

final readonly class RefreshAccessToken
{
    public function __construct(
        private ClientAuthenticator $clients,
        private RefreshTokenRepository $refreshTokens,
        private UserRepository $users,
        private TokenIssuer $tokens,
        private AuditLogger $audit,
        private int $accessTokenTtl,
        private int $refreshTokenTtl,
    ) {
    }

    public function __invoke(string $clientId, string $rawRefreshToken, ActorContext $context): TokenPair
    {
        $client = $this->clients->authenticate($clientId, GrantType::RefreshToken);
        $current = $this->refreshTokens->findByRawToken($rawRefreshToken);

        if (!$current instanceof RefreshToken || $current->oauthClientId !== $client->id) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        if ($current->isRevoked()) {
            // Reuso de um token já rotacionado: pode ter sido roubado, então
            // queima a família inteira -- todo descendente para de funcionar.
            $this->refreshTokens->revokeFamily($current->familyId);
            // Ninguém provou identidade pra fazer esse request -- é o dono do
            // token roubado que sofre, não quem o usou (esse é o desconhecido).
            $this->audit->record(AuditEvent::RefreshTokenReused, null, 'User', $current->userId, [], $context->ipAddress, $context->userAgent);

            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        if ($current->isExpired()) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        $user = $current->userId !== null ? $this->users->findById($current->userId) : null;

        if ($current->userId !== null && !$user instanceof User) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        [$rawNext, $next] = $current->rotate($this->refreshTokenTtl);
        $this->refreshTokens->rotate($current, $next);

        $accessToken = $this->tokens->issueAccessToken(AccessTokenClaims::issue(
            subject: $current->userId ?? $client->clientId,
            clientId: $client->clientId,
            role: $user?->role,
            scopes: $current->scopes,
            ttlSeconds: $this->accessTokenTtl,
        ));

        return new TokenPair($accessToken, $this->accessTokenTtl, $current->scopes, $rawNext);
    }
}
