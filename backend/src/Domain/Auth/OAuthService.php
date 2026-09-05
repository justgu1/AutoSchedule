<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\DTO\TokenPair;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\UserRole;

/**
 * Orquestra o login (email+senha -> tokens) e a renovação via refresh_token.
 * client_credentials (M2M) fica adiado -- ainda não tem consumidor.
 */
final class OAuthService
{
    public function __construct(
        private readonly OAuthClientRepository $clients,
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly TokenIssuer $tokens,
        private readonly AuditLogger $audit,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
    ) {
    }

    public function loginWithPassword(string $clientId, string $email, string $password, string $ipAddress, ?string $userAgent): TokenPair
    {
        $client = $this->requireClient($clientId, GrantType::Password);
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->verifyPassword($password)) {
            $this->audit->record(AuditEvent::LoginFailed, null, ['email' => $email], $ipAddress, $userAgent);

            // De propósito, a mesma mensagem/status pra "email não existe" e "senha
            // errada" -- não pode vazar se a conta existe ou não.
            throw new DomainException('Invalid credentials.', DomainErrorType::Unauthorized);
        }

        $tokenPair = $this->issueTokenPair($client, $user->id, $user->role, $client->allowedScopes);
        $this->audit->record(AuditEvent::LoginSucceeded, $user->id, [], $ipAddress, $userAgent);

        return $tokenPair;
    }

    public function refresh(string $clientId, string $rawRefreshToken, string $ipAddress, ?string $userAgent): TokenPair
    {
        $client = $this->requireClient($clientId, GrantType::RefreshToken);
        $current = $this->refreshTokens->findByRawToken($rawRefreshToken);

        if ($current === null || $current->oauthClientId !== $client->id) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        if ($current->isRevoked()) {
            // Reuso de um token já rotacionado: pode ter sido roubado, então
            // queima a família inteira -- todo descendente para de funcionar.
            $this->refreshTokens->revokeFamily($current->familyId);
            $this->audit->record(AuditEvent::RefreshTokenReused, $current->userId, [], $ipAddress, $userAgent);

            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        if ($current->isExpired()) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        $user = $current->userId !== null ? $this->users->findById($current->userId) : null;

        if ($current->userId !== null && $user === null) {
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

    /** @param list<string> $scopes */
    private function issueTokenPair(OAuthClient $client, string $userId, UserRole $role, array $scopes): TokenPair
    {
        $accessToken = $this->tokens->issueAccessToken(
            AccessTokenClaims::issue($userId, $client->clientId, $role, $scopes, $this->accessTokenTtl),
        );

        [$rawRefreshToken, $refreshToken] = RefreshToken::issue($client->id, $userId, $scopes, $this->refreshTokenTtl);
        $this->refreshTokens->insert($refreshToken);

        return new TokenPair($accessToken, $this->accessTokenTtl, $scopes, $rawRefreshToken);
    }

    private function requireClient(string $clientId, GrantType $grantType): OAuthClient
    {
        $client = $this->clients->findByClientId($clientId);

        if ($client === null || !$client->supportsGrantType($grantType)) {
            throw new DomainException('Invalid client.', DomainErrorType::Unauthorized);
        }

        return $client;
    }
}
