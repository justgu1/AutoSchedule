<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\OAuthService;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Auth\RefreshToken;
use App\Domain\Auth\UserIdentity;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Auth\ValueObjects\GoogleIdentityClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Domain\Users\UserStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OAuthServiceTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryRefreshTokenRepository $refreshTokens;
    private FakeAuditLogger $audit;
    private InMemoryUserIdentityRepository $identities;
    private FakeGoogleIdTokenVerifier $googleVerifier;
    private OAuthClient $webClient;
    private User $customer;

    protected function setUp(): void
    {
        $this->webClient = OAuthClient::create(
            clientId: 'autoschedule-web',
            name: 'AutoSchedule Web',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::Password, GrantType::RefreshToken, GrantType::Google],
            redirectUris: [],
            allowedScopes: ['profile:read'],
        );
        $this->customer = User::register('Ada', 'ada@example.com', null, 'correct-password', UserRole::Customer);

        $this->users = new InMemoryUserRepository($this->customer);
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->identities = new InMemoryUserIdentityRepository();
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $this->audit = new FakeAuditLogger();
    }

    #[Test]
    public function login_with_password_com_credenciais_corretas_emite_tokens(): void
    {
        $tokenPair = $this->makeService()->loginWithPassword('autoschedule-web', 'ada@example.com', 'correct-password', '127.0.0.1', 'phpunit');

        $this->assertNotSame('', $tokenPair->accessToken);
        $this->assertNotNull($tokenPair->refreshToken);
        $this->assertFalse($tokenPair->accountRestored);
        $this->assertSame([AuditEvent::LoginSucceeded], $this->audit->events);
        // Actor e target são a mesma pessoa: quem logou é quem "sofreu" o evento.
        $this->assertSame($this->customer->id, $this->audit->calls[0]['actorId']);
        $this->assertSame($this->customer->id, $this->audit->calls[0]['targetUserId']);
    }

    #[Test]
    public function login_with_password_restaura_conta_trashed_e_audita_antes_do_login(): void
    {
        $this->users->trash($this->customer->id);

        $tokenPair = $this->makeService()->loginWithPassword('autoschedule-web', 'ada@example.com', 'correct-password', '127.0.0.1', 'phpunit');

        $restored = $this->users->findById($this->customer->id);
        assert($restored instanceof \App\Domain\Users\User);
        $this->assertSame(UserStatus::Active, $restored->status);
        $this->assertTrue($tokenPair->accountRestored);
        $this->assertSame([AuditEvent::AccountRestored, AuditEvent::LoginSucceeded], $this->audit->events);
    }

    #[Test]
    public function login_with_password_com_senha_errada_falha_com_mensagem_generica(): void
    {
        try {
            $this->makeService()->loginWithPassword('autoschedule-web', 'ada@example.com', 'wrong-password', '127.0.0.1', 'phpunit');
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
            $this->assertSame('Invalid credentials.', $exception->getMessage());
            $this->assertSame([AuditEvent::LoginFailed], $this->audit->events);
            // Identidade não provada (senha errada) -- sem actor, mas o alvo é
            // conhecido porque o email existe.
            $this->assertNull($this->audit->calls[0]['actorId']);
            $this->assertSame($this->customer->id, $this->audit->calls[0]['targetUserId']);
        }
    }

    #[Test]
    public function login_with_password_com_email_inexistente_falha_com_a_mesma_mensagem(): void
    {
        try {
            $this->makeService()->loginWithPassword('autoschedule-web', 'nobody@example.com', 'whatever', '127.0.0.1', 'phpunit');
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame('Invalid credentials.', $exception->getMessage());
            // Nem o email existe -- nem actor nem target pra apontar.
            $this->assertNull($this->audit->calls[0]['actorId']);
            $this->assertNull($this->audit->calls[0]['targetUserId']);
        }
    }

    #[Test]
    public function login_with_password_rejeita_client_que_nao_suporta_o_grant(): void
    {
        $mobileClient = OAuthClient::create(
            clientId: 'autoschedule-mobile',
            name: 'AutoSchedule Mobile',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::RefreshToken], // sem Password de propósito
            redirectUris: [],
            allowedScopes: [],
        );
        $service = new OAuthService(
            clients: new InMemoryOAuthClientRepository([$this->webClient, $mobileClient]),
            users: $this->users,
            refreshTokens: $this->refreshTokens,
            identities: $this->identities,
            tokens: new FakeTokenIssuer(),
            googleVerifier: $this->googleVerifier,
            audit: $this->audit,
            accessTokenTtl: 900,
            refreshTokenTtl: 1_209_600,
        );

        $this->expectException(DomainException::class);
        $service->loginWithPassword('autoschedule-mobile', 'ada@example.com', 'correct-password', '127.0.0.1', 'phpunit');
    }

    #[Test]
    public function refresh_rotaciona_o_token_e_o_anterior_para_de_funcionar(): void
    {
        $service = $this->makeService();
        $original = $service->loginWithPassword('autoschedule-web', 'ada@example.com', 'correct-password', '127.0.0.1', 'phpunit');

        $rotated = $service->refresh('autoschedule-web', $original->refreshToken, '127.0.0.1', 'phpunit');

        $this->assertNotSame($original->refreshToken, $rotated->refreshToken);
    }

    #[Test]
    public function refresh_com_token_ja_rotacionado_revoga_a_familia_inteira(): void
    {
        $service = $this->makeService();
        $original = $service->loginWithPassword('autoschedule-web', 'ada@example.com', 'correct-password', '127.0.0.1', 'phpunit');
        $rotated = $service->refresh('autoschedule-web', $original->refreshToken, '127.0.0.1', 'phpunit');

        // Reusar o token já rotacionado aciona a detecção de reuso; deve
        // também queimar o token que saiu dessa mesma rotação.
        try {
            $service->refresh('autoschedule-web', $original->refreshToken, '127.0.0.1', 'phpunit');
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertContains(AuditEvent::RefreshTokenReused, $this->audit->events);
        $reuseCall = $this->audit->calls[array_search(AuditEvent::RefreshTokenReused, $this->audit->events, true)];
        // Quem reusou o token não provou identidade nenhuma -- sem actor. O
        // alvo é o dono da família de tokens, não quem reusou.
        $this->assertNull($reuseCall['actorId']);
        $this->assertSame($this->customer->id, $reuseCall['targetUserId']);

        $this->expectException(DomainException::class);
        $service->refresh('autoschedule-web', $rotated->refreshToken, '127.0.0.1', 'phpunit');
    }

    #[Test]
    public function logout_revoga_o_refresh_token_e_o_reuso_subsequente_falha(): void
    {
        $service = $this->makeService();
        $original = $service->loginWithPassword('autoschedule-web', 'ada@example.com', 'correct-password', '127.0.0.1', 'phpunit');

        $service->logout($original->refreshToken);

        $this->expectException(DomainException::class);
        $service->refresh('autoschedule-web', $original->refreshToken, '127.0.0.1', 'phpunit');
    }

    #[Test]
    public function logout_com_token_inexistente_nao_lanca_excecao(): void
    {
        $this->makeService()->logout('token-que-nunca-existiu');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function login_with_google_com_identidade_ja_linkada_loga_na_conta_existente(): void
    {
        $this->identities->insert(UserIdentity::link($this->customer->id, 'google', 'google-sub-1', 'ada@example.com'));
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-1', 'ada@example.com', true, 'Ada');

        $tokenPair = $this->makeService()->loginWithGoogle('autoschedule-web', 'fake-id-token', '127.0.0.1', 'phpunit');

        $this->assertNotSame('', $tokenPair->accessToken);
        $this->assertSame([AuditEvent::LoginSucceeded], $this->audit->events);
        $this->assertSame($this->customer->id, $this->audit->calls[0]['actorId']);
    }

    #[Test]
    public function login_with_google_restaura_conta_trashed_da_identidade_ja_linkada(): void
    {
        $this->identities->insert(UserIdentity::link($this->customer->id, 'google', 'google-sub-1', 'ada@example.com'));
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-1', 'ada@example.com', true, 'Ada');
        $this->users->trash($this->customer->id);

        $tokenPair = $this->makeService()->loginWithGoogle('autoschedule-web', 'fake-id-token', '127.0.0.1', 'phpunit');

        $restored = $this->users->findById($this->customer->id);
        assert($restored instanceof \App\Domain\Users\User);
        $this->assertSame(UserStatus::Active, $restored->status);
        $this->assertTrue($tokenPair->accountRestored);
        $this->assertSame([AuditEvent::AccountRestored, AuditEvent::LoginSucceeded], $this->audit->events);
    }

    #[Test]
    public function login_with_google_com_email_de_conta_existente_linka_sem_mudar_role(): void
    {
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-2', 'ada@example.com', true, 'Ada');

        $this->makeService()->loginWithGoogle('autoschedule-web', 'fake-id-token', '127.0.0.1', 'phpunit');

        $linked = $this->identities->findByProvider('google', 'google-sub-2');
        $this->assertNotNull($linked);
        $this->assertSame($this->customer->id, $linked->userId);
        // Role da conta não muda só por linkar o Google.
        $this->assertSame(UserRole::Customer, $this->users->findById($this->customer->id)->role);
    }

    #[Test]
    public function login_with_google_com_email_novo_cria_conta_customer(): void
    {
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-3', 'nova@example.com', true, 'Nova Pessoa');

        $this->makeService()->loginWithGoogle('autoschedule-web', 'fake-id-token', '127.0.0.1', 'phpunit');

        $created = $this->users->findByEmail('nova@example.com');
        $this->assertNotNull($created);
        $this->assertSame(UserRole::Customer, $created->role);
        $this->assertSame([AuditEvent::UserCreated], $this->audit->events);
        $this->assertNotNull($this->identities->findByProvider('google', 'google-sub-3'));
    }

    #[Test]
    public function login_with_google_rejeita_email_nao_verificado(): void
    {
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-4', 'naoverificado@example.com', false, 'Alguém');

        try {
            $this->makeService()->loginWithGoogle('autoschedule-web', 'fake-id-token', '127.0.0.1', 'phpunit');
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
            $this->assertNull($this->users->findByEmail('naoverificado@example.com'));
        }
    }

    #[Test]
    public function login_with_google_rejeita_client_sem_esse_grant(): void
    {
        $clientSemGoogle = OAuthClient::create(
            clientId: 'autoschedule-no-google',
            name: 'Sem Google',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::Password], // sem Google de propósito
            redirectUris: [],
            allowedScopes: [],
        );
        $service = new OAuthService(
            clients: new InMemoryOAuthClientRepository([$clientSemGoogle]),
            users: $this->users,
            refreshTokens: $this->refreshTokens,
            identities: $this->identities,
            tokens: new FakeTokenIssuer(),
            googleVerifier: $this->googleVerifier,
            audit: $this->audit,
            accessTokenTtl: 900,
            refreshTokenTtl: 1_209_600,
        );

        $this->expectException(DomainException::class);
        $service->loginWithGoogle('autoschedule-no-google', 'fake-id-token', '127.0.0.1', 'phpunit');
    }

    #[Test]
    public function client_credentials_com_secret_correto_emite_token_sem_refresh(): void
    {
        $service = $this->makeServiceWithServiceClient('correct-secret');

        $tokenPair = $service->clientCredentials('autoschedule-service', 'correct-secret', '127.0.0.1', 'phpunit');

        $this->assertNotSame('', $tokenPair->accessToken);
        $this->assertNull($tokenPair->refreshToken);
        $this->assertSame(['service:internal'], $tokenPair->scopes);
        $this->assertSame([AuditEvent::ServiceTokenIssued], $this->audit->events);
        // Não é um usuário se autenticando -- sem actor nem target.
        $this->assertNull($this->audit->calls[0]['actorId']);
        $this->assertNull($this->audit->calls[0]['targetUserId']);
    }

    #[Test]
    public function client_credentials_com_secret_errado_falha_com_mensagem_generica(): void
    {
        $service = $this->makeServiceWithServiceClient('correct-secret');

        try {
            $service->clientCredentials('autoschedule-service', 'wrong-secret', '127.0.0.1', 'phpunit');
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
            $this->assertSame('Invalid client credentials.', $exception->getMessage());
        }
    }

    #[Test]
    public function client_credentials_rejeita_client_publico(): void
    {
        $service = $this->makeService(); // só tem o autoschedule-web, público

        $this->expectException(DomainException::class);
        $service->clientCredentials('autoschedule-web', 'whatever', '127.0.0.1', 'phpunit');
    }

    #[Test]
    public function client_credentials_rejeita_client_sem_esse_grant(): void
    {
        $client = OAuthClient::create(
            clientId: 'autoschedule-no-m2m',
            name: 'Sem M2M',
            type: ClientType::Confidential,
            allowedGrantTypes: [GrantType::RefreshToken], // sem ClientCredentials de propósito
            redirectUris: [],
            allowedScopes: ['service:internal'],
            plainSecret: 'correct-secret',
        );
        $service = new OAuthService(
            clients: new InMemoryOAuthClientRepository([$client]),
            users: $this->users,
            refreshTokens: $this->refreshTokens,
            identities: $this->identities,
            tokens: new FakeTokenIssuer(),
            googleVerifier: $this->googleVerifier,
            audit: $this->audit,
            accessTokenTtl: 900,
            refreshTokenTtl: 1_209_600,
        );

        $this->expectException(DomainException::class);
        $service->clientCredentials('autoschedule-no-m2m', 'correct-secret', '127.0.0.1', 'phpunit');
    }

    private function makeServiceWithServiceClient(string $plainSecret): OAuthService
    {
        $serviceClient = OAuthClient::create(
            clientId: 'autoschedule-service',
            name: 'AutoSchedule Service',
            type: ClientType::Confidential,
            allowedGrantTypes: [GrantType::ClientCredentials],
            redirectUris: [],
            allowedScopes: ['service:internal'],
            plainSecret: $plainSecret,
        );

        return new OAuthService(
            clients: new InMemoryOAuthClientRepository([$this->webClient, $serviceClient]),
            users: $this->users,
            refreshTokens: $this->refreshTokens,
            identities: $this->identities,
            tokens: new FakeTokenIssuer(),
            googleVerifier: $this->googleVerifier,
            audit: $this->audit,
            accessTokenTtl: 900,
            refreshTokenTtl: 1_209_600,
        );
    }

    private function makeService(): OAuthService
    {
        return new OAuthService(
            clients: new InMemoryOAuthClientRepository([$this->webClient]),
            users: $this->users,
            refreshTokens: $this->refreshTokens,
            identities: $this->identities,
            tokens: new FakeTokenIssuer(),
            googleVerifier: $this->googleVerifier,
            audit: $this->audit,
            accessTokenTtl: 900,
            refreshTokenTtl: 1_209_600,
        );
    }
}

final readonly class InMemoryOAuthClientRepository implements OAuthClientRepository
{
    /** @param list<OAuthClient> $clients */
    public function __construct(private array $clients)
    {
    }

    public function findByClientId(string $clientId): ?OAuthClient
    {
        foreach ($this->clients as $client) {
            if ($client->clientId === $clientId) {
                return $client;
            }
        }

        return null;
    }
}

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $byId = [];

    public function __construct(User ...$users)
    {
        foreach ($users as $user) {
            $this->byId[$user->id] = $user;
        }
    }

    public function findById(string $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function existsByEmail(string $email): bool
    {
        return $this->findByEmail($email) instanceof \App\Domain\Users\User;
    }

    public function insert(User $user): void
    {
        $this->byId[$user->id] = $user;
    }

    public function update(User $user): void
    {
        $this->byId[$user->id] = $user;
    }

    public function anonymizeAndSoftDelete(string $id): void
    {
        unset($this->byId[$id]);
    }

    public function trash(string $id): void
    {
        if (($user = $this->byId[$id] ?? null) instanceof User) {
            $this->byId[$id] = $this->withStatus($user, UserStatus::Trashed, new \DateTimeImmutable());
        }
    }

    public function restore(string $id): void
    {
        if (($user = $this->byId[$id] ?? null) instanceof User) {
            $this->byId[$id] = $this->withStatus($user, UserStatus::Active, null);
        }
    }

    public function findPurgeEligible(int $graceDays, \DateTimeImmutable $now): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (User $user): bool => $user->isEligibleForPurge($graceDays, $now),
        ));
    }

    private function withStatus(User $user, UserStatus $status, ?\DateTimeImmutable $deletedAt): User
    {
        return new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            passwordHash: $user->passwordHash,
            role: $user->role,
            passwordSetAt: $user->passwordSetAt,
            emailVerifiedAt: $user->emailVerifiedAt,
            createdAt: $user->createdAt,
            updatedAt: new \DateTimeImmutable(),
            deletedAt: $deletedAt,
            status: $status,
            anonymizedAt: $user->anonymizedAt,
        );
    }

    public function findPage(int $limit, int $offset): array
    {
        return array_slice(array_values($this->byId), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->byId);
    }

    public function countByRole(UserRole $role): int
    {
        return count(array_filter($this->byId, static fn (User $user): bool => $user->role === $role));
    }
}

final class InMemoryRefreshTokenRepository implements RefreshTokenRepository
{
    /** @var array<string, RefreshToken> */
    private array $byHash = [];

    public function insert(RefreshToken $token): void
    {
        $this->byHash[$token->tokenHash] = $token;
    }

    public function findByRawToken(string $rawToken): ?RefreshToken
    {
        return $this->byHash[hash('sha256', $rawToken)] ?? null;
    }

    public function rotate(RefreshToken $current, RefreshToken $next): void
    {
        $stored = $this->byHash[$current->tokenHash] ?? null;

        if ($stored === null || $stored->isRevoked()) {
            throw new DomainException('Invalid or expired refresh token.', DomainErrorType::Unauthorized);
        }

        $this->byHash[$current->tokenHash] = new RefreshToken(
            $current->id,
            $current->tokenHash,
            $current->familyId,
            $current->oauthClientId,
            $current->userId,
            $current->scopes,
            $current->expiresAt,
            new \DateTimeImmutable(),
            $next->id,
        );
        $this->byHash[$next->tokenHash] = $next;
    }

    public function revokeFamily(string $familyId): void
    {
        foreach ($this->byHash as $hash => $token) {
            if ($token->familyId === $familyId && !$token->isRevoked()) {
                $this->byHash[$hash] = new RefreshToken(
                    $token->id,
                    $token->tokenHash,
                    $token->familyId,
                    $token->oauthClientId,
                    $token->userId,
                    $token->scopes,
                    $token->expiresAt,
                    new \DateTimeImmutable(),
                    $token->replacedById,
                );
            }
        }
    }

    public function revokeAllForUser(string $userId): void
    {
        foreach ($this->byHash as $hash => $token) {
            if ($token->userId === $userId && !$token->isRevoked()) {
                $this->byHash[$hash] = new RefreshToken(
                    $token->id,
                    $token->tokenHash,
                    $token->familyId,
                    $token->oauthClientId,
                    $token->userId,
                    $token->scopes,
                    $token->expiresAt,
                    new \DateTimeImmutable(),
                    $token->replacedById,
                );
            }
        }
    }
}

final class FakeAuditLogger implements AuditLogger
{
    /** @var list<AuditEvent> */
    public array $events = [];

    /** @var list<array{event: AuditEvent, actorId: ?string, targetUserId: ?string}> */
    public array $calls = [];

    /** @param array<string, mixed> $context */
    public function record(AuditEvent $event, ?string $actorId, ?string $targetUserId, array $context, string $ipAddress, ?string $userAgent): void
    {
        $this->events[] = $event;
        $this->calls[] = ['event' => $event, 'actorId' => $actorId, 'targetUserId' => $targetUserId];
    }
}

/** Só honra o contrato da porta, sem codificação JWT real -- OAuthService não precisa de mais que isso. */
final class FakeTokenIssuer implements TokenIssuer
{
    /** @var array<string, AccessTokenClaims> */
    private array $issued = [];

    public function issueAccessToken(AccessTokenClaims $claims): string
    {
        $this->issued[$claims->jti] = $claims;

        return $claims->jti;
    }

    public function decodeAccessToken(string $token): AccessTokenClaims
    {
        return $this->issued[$token] ?? throw new DomainException('Invalid or expired access token.', DomainErrorType::Unauthorized);
    }
}

final class InMemoryUserIdentityRepository implements UserIdentityRepository
{
    /** @var array<string, UserIdentity> */
    private array $byKey = [];

    public function findByProvider(string $provider, string $providerUserId): ?UserIdentity
    {
        return $this->byKey["{$provider}:{$providerUserId}"] ?? null;
    }

    public function insert(UserIdentity $identity): void
    {
        $this->byKey["{$identity->provider}:{$identity->providerUserId}"] = $identity;
    }
}

/** Devolve $nextClaims sem verificar assinatura nenhuma -- OAuthService não precisa de mais que isso pra testar o fluxo de login. */
final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public ?GoogleIdentityClaims $nextClaims = null;

    public function verify(string $idToken): GoogleIdentityClaims
    {
        return $this->nextClaims ?? throw new DomainException('Invalid Google credential.', DomainErrorType::Unauthorized);
    }
}
