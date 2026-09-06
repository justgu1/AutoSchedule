<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\DTO\TokenPair;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Domain\Users\UserStatus;

/**
 * Orquestra o login (email+senha -> tokens), a renovação via refresh_token, o
 * client_credentials (M2M, sem usuário, sem refresh token) e o login social
 * via Google (conta existente por e-mail linka, e-mail novo cria customer).
 */
final readonly class OAuthService
{
    public function __construct(
        private OAuthClientRepository $clients,
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private UserIdentityRepository $identities,
        private TokenIssuer $tokens,
        private GoogleIdTokenVerifier $googleVerifier,
        private AuditLogger $audit,
        private int $accessTokenTtl,
        private int $refreshTokenTtl,
    ) {
    }

    public function loginWithPassword(string $clientId, string $email, string $password, string $ipAddress, ?string $userAgent): TokenPair
    {
        $client = $this->requireClient($clientId, GrantType::Password);
        $user = $this->users->findByEmail($email);

        if (!$user instanceof \App\Domain\Users\User || !$user->verifyPassword($password)) {
            // Identidade não provada -- sem actor. $user?->id como alvo quando o
            // email existe (senha errada), null quando nem a conta existe.
            $this->audit->record(AuditEvent::LoginFailed, null, $user?->id, ['email' => $email], $ipAddress, $userAgent);

            // De propósito, a mesma mensagem/status pra "email não existe" e "senha
            // errada" -- não pode vazar se a conta existe ou não.
            throw new DomainException('Invalid credentials.', DomainErrorType::Unauthorized);
        }

        $restored = $this->restoreIfTrashed($user, $ipAddress, $userAgent);

        $tokenPair = $this->issueTokenPair($client, $user->id, $user->role, $client->allowedScopes, $restored);
        $this->audit->record(AuditEvent::LoginSucceeded, $user->id, $user->id, [], $ipAddress, $userAgent);

        return $tokenPair;
    }

    /** Login com sucesso é a chance de recuperar a conta -- se ainda não foi anonimizada em definitivo, sai da lixeira aqui, sem exigir passo extra do usuário. Devolve se restaurou, pro token final avisar o frontend. */
    private function restoreIfTrashed(User $user, string $ipAddress, ?string $userAgent): bool
    {
        if ($user->status !== UserStatus::Trashed || $user->anonymizedAt instanceof \DateTimeImmutable) {
            return false;
        }

        $this->users->restore($user->id);
        $this->audit->record(AuditEvent::AccountRestored, $user->id, $user->id, [], $ipAddress, $userAgent);

        return true;
    }

    public function refresh(string $clientId, string $rawRefreshToken, string $ipAddress, ?string $userAgent): TokenPair
    {
        $client = $this->requireClient($clientId, GrantType::RefreshToken);
        $current = $this->refreshTokens->findByRawToken($rawRefreshToken);

        if (!$current instanceof \App\Domain\Auth\RefreshToken || $current->oauthClientId !== $client->id) {
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

        if ($current->userId !== null && !$user instanceof \App\Domain\Users\User) {
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
     * E-mail verificado pelo Google já prova posse -- conta existente com o
     * mesmo e-mail é linkada automaticamente (sem mudar role), e-mail novo
     * cria conta `customer` (mesma regra do registro manual: customer só
     * precisa de login pra ver histórico). Senha aleatória inutilizável --
     * conta social-only até pedir "esqueci minha senha" se quiser também
     * logar com senha.
     */
    public function loginWithGoogle(string $clientId, string $idToken, string $ipAddress, ?string $userAgent): TokenPair
    {
        $client = $this->requireClient($clientId, GrantType::Google);
        $claims = $this->googleVerifier->verify($idToken);

        if (!$claims->emailVerified) {
            throw new DomainException('Google account email is not verified.', DomainErrorType::Unauthorized);
        }

        $identity = $this->identities->findByProvider('google', $claims->subject);

        if ($identity instanceof \App\Domain\Auth\UserIdentity) {
            $user = $this->users->findById($identity->userId);

            if (!$user instanceof \App\Domain\Users\User) {
                throw new DomainException('Invalid Google credential.', DomainErrorType::Unauthorized);
            }

            $restored = $this->restoreIfTrashed($user, $ipAddress, $userAgent);
            $this->audit->record(AuditEvent::LoginSucceeded, $user->id, $user->id, ['via' => 'google'], $ipAddress, $userAgent);

            return $this->issueTokenPair($client, $user->id, $user->role, $client->allowedScopes, $restored);
        }

        $existingByEmail = $this->users->findByEmail($claims->email);

        if ($existingByEmail instanceof \App\Domain\Users\User) {
            $this->identities->insert(UserIdentity::link($existingByEmail->id, 'google', $claims->subject, $claims->email));
            $restored = $this->restoreIfTrashed($existingByEmail, $ipAddress, $userAgent);
            $this->audit->record(AuditEvent::LoginSucceeded, $existingByEmail->id, $existingByEmail->id, ['via' => 'google', 'linked' => true], $ipAddress, $userAgent);

            return $this->issueTokenPair($client, $existingByEmail->id, $existingByEmail->role, $client->allowedScopes, $restored);
        }

        // Ninguém sabe/usa essa senha -- só ocupa o campo NOT NULL; login dessa conta é sempre via Google até um reset trocar por uma real.
        $newUser = User::register($claims->name, $claims->email, null, bin2hex(random_bytes(32)), UserRole::Customer);
        $this->users->insert($newUser);
        $this->identities->insert(UserIdentity::link($newUser->id, 'google', $claims->subject, $claims->email));
        $this->audit->record(AuditEvent::UserCreated, $newUser->id, $newUser->id, ['role' => $newUser->role->value, 'via' => 'google'], $ipAddress, $userAgent);

        return $this->issueTokenPair($client, $newUser->id, $newUser->role, $client->allowedScopes);
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

        if ($current instanceof \App\Domain\Auth\RefreshToken) {
            $this->refreshTokens->revokeFamily($current->familyId);
        }
    }

    /** @param list<string> $scopes */
    private function issueTokenPair(OAuthClient $client, string $userId, UserRole $role, array $scopes, bool $accountRestored = false): TokenPair
    {
        $accessToken = $this->tokens->issueAccessToken(
            AccessTokenClaims::issue($userId, $client->clientId, $role, $scopes, $this->accessTokenTtl),
        );

        [$rawRefreshToken, $refreshToken] = RefreshToken::issue($client->id, $userId, $scopes, $this->refreshTokenTtl);
        $this->refreshTokens->insert($refreshToken);

        return new TokenPair($accessToken, $this->accessTokenTtl, $scopes, $rawRefreshToken, $accountRestored);
    }

    private function requireClient(string $clientId, GrantType $grantType): OAuthClient
    {
        $client = $this->clients->findByClientId($clientId);

        if (!$client instanceof \App\Domain\Auth\OAuthClient || !$client->supportsGrantType($grantType)) {
            throw new DomainException('Invalid client.', DomainErrorType::Unauthorized);
        }

        return $client;
    }
}
