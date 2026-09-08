<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\DTO\TokenPair;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\RefreshToken;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\User\UserRole;

final readonly class TokenPairIssuer
{
    public function __construct(
        private TokenIssuer $tokens,
        private RefreshTokenRepository $refreshTokens,
        private TokenTtl $ttl,
    ) {
    }

    /** @param list<string> $scopes */
    public function issue(OAuthClient $client, string $userId, UserRole $role, array $scopes, bool $accountRestored = false): TokenPair
    {
        $accessToken = $this->tokens->issueAccessToken(AccessTokenClaims::issue(
            subject: $userId,
            clientId: $client->clientId,
            role: $role,
            scopes: $scopes,
            ttlSeconds: $this->ttl->accessSeconds,
        ));

        [$rawRefreshToken, $refreshToken] = RefreshToken::issue($client->id, $userId, $scopes, $this->ttl->refreshSeconds);
        $this->refreshTokens->insert($refreshToken);

        return new TokenPair($accessToken, $this->ttl->accessSeconds, $scopes, $rawRefreshToken, $accountRestored);
    }
}
