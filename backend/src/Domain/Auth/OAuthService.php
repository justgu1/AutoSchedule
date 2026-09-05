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
 * Orquestra o login (email+senha -> tokens), a renovação via refresh_token e
 * o client_credentials (M2M, sem usuário, sem refresh token).
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
            // Identidade não provada -- sem actor. $user?->id como alvo quando o
            // email existe (senha errada), null quando nem a conta existe.
            $this->audit->record(AuditEvent::LoginFailed, null, $user?->id, ['email' => $email], $ipAddress, $userAgent);

            // De propósito, a mesma mensagem/status pra "email não existe" e "senha
            // errada" -- não pode vazar se a conta existe ou não.
            throw new DomainException('Invalid credentials.', DomainErrorType::Unauthorized);
        }

        $tokenPair = $this->issueTokenPair($client, $user->id, $user->role, $client->allowedScopes);
        $this->audit->record(AuditEvent::LoginSucceeded, $user->id, $user->id, [], $ipAddress, $userAgent);

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
            // Ninguém provou identidade pra fazer esse request -- é o dono do
            // token roubado que sofre, não quem o usou (esse é o desconhecido).
            $this->audit->record(AuditEvent::RefreshTokenReused, null, $current->userId, [], $ipAddress, $userAgent);

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

    /**
     * M2M: sem usuário, sem sessão pra renovar -- só access token, sem refresh
     * token. Client tem que ser confidencial (guarda segredo) e provar posse
     * dele; mesma mensagem genérica de sempre pra não vazar se o client_id existe.
     */
    public function clientCredentials(string $clientId, string $clientSecret, string $ipAddress, ?string $userAgent): TokenPair
    {
        $client = $this->requireClient($clientId, GrantType::ClientCredentials);

        if ($client->type !== ClientType::Confidential || !$client->verifySecret($clientSecret)) {
            throw new DomainException('Invalid client credentials.', DomainErrorType::Unauthorized);
        }

        $accessToken = $this->tokens->issueAccessToken(AccessTokenClaims::issue(
            subject: $client->clientId,
            clientId: $client->clientId,
            role: null,
            scopes: $client->allowedScopes,
            ttlSeconds: $this->accessTokenTtl,
        ));

        // actorId/userId nulos -- não é um usuário, é o client se autenticando; o client_id vai no context.
        $this->audit->record(AuditEvent::ServiceTokenIssued, null, null, ['client_id' => $client->clientId], $ipAddress, $userAgent);

        return new TokenPair($accessToken, $this->accessTokenTtl, $client->allowedScopes);
    }

    /** Sempre "sucesso" do ponto de vista do client -- token já inválido/inexistente não é erro, só não tem mais nada a revogar. */
    public function logout(string $rawRefreshToken): void
    {
        $current = $this->refreshTokens->findByRawToken($rawRefreshToken);

        if ($current !== null) {
            $this->refreshTokens->revokeFamily($current->familyId);
        }
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
