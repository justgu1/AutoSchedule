<?php

declare(strict_types=1);

namespace Tests\Application\Auth;

use App\Application\Auth\AccountRestorer;
use App\Application\Auth\ClientAuthenticator;
use App\Application\Auth\DTO\TokenPair;
use App\Application\Auth\IssueServiceToken;
use App\Application\Auth\LoginWithGoogle;
use App\Application\Auth\LoginWithPassword;
use App\Application\Auth\Logout;
use App\Application\Auth\RefreshAccessToken;
use App\Application\Auth\TokenPairIssuer;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Auth\RefreshToken;
use App\Domain\Auth\UserIdentity;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Auth\ValueObjects\GoogleIdentityClaims;
use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Email;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuditLogger;

/** Os quatro fluxos de `POST /oauth/token` mais o logout, cada um no seu caso de uso, sobre os mesmos dublês. */
final class OAuthFlowsTest extends TestCase
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
        $this->customer = User::register('Ada', new Email('ada@example.com'), null, 'correct-password', UserRole::Customer);

        $this->users = new InMemoryUserRepository($this->customer);
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->identities = new InMemoryUserIdentityRepository();
        $this->googleVerifier = new FakeGoogleIdTokenVerifier();
        $this->audit = new FakeAuditLogger();
    }

    #[Test]
    public function login_with_password_com_credenciais_corretas_emite_tokens(): void
    {
        $tokenPair = ($this->loginWithPassword())('autoschedule-web', 'ada@example.com', 'correct-password', $this->context());

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

        $tokenPair = ($this->loginWithPassword())('autoschedule-web', 'ada@example.com', 'correct-password', $this->context());

        $restored = $this->users->findById($this->customer->id);
        assert($restored instanceof User);
        $this->assertSame(TrashableStatus::Active, $restored->trash->status);
        $this->assertTrue($tokenPair->accountRestored);
        $this->assertSame([AuditEvent::AccountRestored, AuditEvent::LoginSucceeded], $this->audit->events);
    }

    #[Test]
    public function login_with_password_com_senha_errada_falha_com_mensagem_generica(): void
    {
        try {
            ($this->loginWithPassword())('autoschedule-web', 'ada@example.com', 'wrong-password', $this->context());
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
            ($this->loginWithPassword())('autoschedule-web', 'nobody@example.com', 'whatever', $this->context());
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

        $this->expectException(DomainException::class);
        ($this->loginWithPassword($this->clients($this->webClient, $mobileClient)))('autoschedule-mobile', 'ada@example.com', 'correct-password', $this->context());
    }

    #[Test]
    public function refresh_rotaciona_o_token_e_o_anterior_para_de_funcionar(): void
    {
        $original = $this->refreshTokenOf(($this->loginWithPassword())('autoschedule-web', 'ada@example.com', 'correct-password', $this->context()));

        $rotated = ($this->refreshAccessToken())('autoschedule-web', $original, $this->context());

        $this->assertNotSame($original, $rotated->refreshToken);
    }

    #[Test]
    public function refresh_com_token_ja_rotacionado_revoga_a_familia_inteira(): void
    {
        $original = $this->refreshTokenOf(($this->loginWithPassword())('autoschedule-web', 'ada@example.com', 'correct-password', $this->context()));
        $rotated = $this->refreshTokenOf(($this->refreshAccessToken())('autoschedule-web', $original, $this->context()));

        // Reusar o token já rotacionado aciona a detecção de reuso; deve
        // também queimar o token que saiu dessa mesma rotação.
        try {
            ($this->refreshAccessToken())('autoschedule-web', $original, $this->context());
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertContains(AuditEvent::RefreshTokenReused, $this->audit->events);
        $reuseIndex = array_search(AuditEvent::RefreshTokenReused, $this->audit->events, true);

        if ($reuseIndex === false) {
            self::fail('Expected a RefreshTokenReused audit event.');
        }

        $reuseCall = $this->audit->calls[$reuseIndex];
        // Quem reusou o token não provou identidade nenhuma -- sem actor. O
        // alvo é o dono da família de tokens, não quem reusou.
        $this->assertNull($reuseCall['actorId']);
        $this->assertSame($this->customer->id, $reuseCall['targetUserId']);

        $this->expectException(DomainException::class);
        ($this->refreshAccessToken())('autoschedule-web', $rotated, $this->context());
    }

    #[Test]
    public function logout_revoga_o_refresh_token_e_o_reuso_subsequente_falha(): void
    {
        $original = $this->refreshTokenOf(($this->loginWithPassword())('autoschedule-web', 'ada@example.com', 'correct-password', $this->context()));

        ($this->logout())($original);

        $this->expectException(DomainException::class);
        ($this->refreshAccessToken())('autoschedule-web', $original, $this->context());
    }

    #[Test]
    public function logout_com_token_inexistente_nao_lanca_excecao(): void
    {
        ($this->logout())('token-que-nunca-existiu');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function login_with_google_com_identidade_ja_linkada_loga_na_conta_existente(): void
    {
        $this->identities->insert(UserIdentity::link($this->customer->id, 'google', 'google-sub-1', new Email('ada@example.com')));
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-1', 'ada@example.com', true, 'Ada');

        $tokenPair = ($this->loginWithGoogle())('autoschedule-web', 'fake-id-token', $this->context());

        $this->assertNotSame('', $tokenPair->accessToken);
        $this->assertSame([AuditEvent::LoginSucceeded], $this->audit->events);
        $this->assertSame($this->customer->id, $this->audit->calls[0]['actorId']);
    }

    #[Test]
    public function login_with_google_restaura_conta_trashed_da_identidade_ja_linkada(): void
    {
        $this->identities->insert(UserIdentity::link($this->customer->id, 'google', 'google-sub-1', new Email('ada@example.com')));
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-1', 'ada@example.com', true, 'Ada');
        $this->users->trash($this->customer->id);

        $tokenPair = ($this->loginWithGoogle())('autoschedule-web', 'fake-id-token', $this->context());

        $restored = $this->users->findById($this->customer->id);
        assert($restored instanceof User);
        $this->assertSame(TrashableStatus::Active, $restored->trash->status);
        $this->assertTrue($tokenPair->accountRestored);
        $this->assertSame([AuditEvent::AccountRestored, AuditEvent::LoginSucceeded], $this->audit->events);
    }

    #[Test]
    public function login_with_google_com_email_de_conta_existente_linka_sem_mudar_role(): void
    {
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-2', 'ada@example.com', true, 'Ada');

        ($this->loginWithGoogle())('autoschedule-web', 'fake-id-token', $this->context());

        $linked = $this->identities->findByProvider('google', 'google-sub-2');
        $this->assertNotNull($linked);
        $this->assertSame($this->customer->id, $linked->userId);
        // Role da conta não muda só por linkar o Google.
        $reloaded = $this->users->findById($this->customer->id);
        assert($reloaded instanceof User);
        $this->assertSame(UserRole::Customer, $reloaded->role);
    }

    #[Test]
    public function login_with_google_com_email_novo_cria_conta_customer(): void
    {
        $this->googleVerifier->nextClaims = new GoogleIdentityClaims('google-sub-3', 'nova@example.com', true, 'Nova Pessoa');

        ($this->loginWithGoogle())('autoschedule-web', 'fake-id-token', $this->context());

        $created = $this->users->findByEmail(new Email('nova@example.com'));
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
            ($this->loginWithGoogle())('autoschedule-web', 'fake-id-token', $this->context());
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
            $this->assertNull($this->users->findByEmail(new Email('naoverificado@example.com')));
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

        $this->expectException(DomainException::class);
        ($this->loginWithGoogle($this->clients($clientSemGoogle)))('autoschedule-no-google', 'fake-id-token', $this->context());
    }

    #[Test]
    public function client_credentials_com_secret_correto_emite_token_sem_refresh(): void
    {
        $tokenPair = ($this->issueServiceToken($this->clients($this->webClient, $this->serviceClient('correct-secret'))))(
            'autoschedule-service',
            'correct-secret',
            $this->context(),
        );

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
        try {
            ($this->issueServiceToken($this->clients($this->webClient, $this->serviceClient('correct-secret'))))(
                'autoschedule-service',
                'wrong-secret',
                $this->context(),
            );
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
            $this->assertSame('Invalid client credentials.', $exception->getMessage());
        }
    }

    #[Test]
    public function client_credentials_rejeita_client_publico(): void
    {
        $this->expectException(DomainException::class);
        // Só o autoschedule-web, que é público.
        ($this->issueServiceToken($this->clients($this->webClient)))('autoschedule-web', 'whatever', $this->context());
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

        $this->expectException(DomainException::class);
        ($this->issueServiceToken($this->clients($client)))('autoschedule-no-m2m', 'correct-secret', $this->context());
    }

    private function context(): ActorContext
    {
        return new ActorContext(ipAddress: '127.0.0.1', userAgent: 'phpunit');
    }

    private function refreshTokenOf(TokenPair $tokenPair): string
    {
        if ($tokenPair->refreshToken === null) {
            self::fail('Expected the token pair to carry a refresh token.');
        }

        return $tokenPair->refreshToken;
    }

    private function serviceClient(string $plainSecret): OAuthClient
    {
        return OAuthClient::create(
            clientId: 'autoschedule-service',
            name: 'AutoSchedule Service',
            type: ClientType::Confidential,
            allowedGrantTypes: [GrantType::ClientCredentials],
            redirectUris: [],
            allowedScopes: ['service:internal'],
            plainSecret: $plainSecret,
        );
    }

    private function clients(OAuthClient ...$clients): ClientAuthenticator
    {
        return new ClientAuthenticator(new InMemoryOAuthClientRepository(array_values($clients)));
    }

    private function tokenPairs(): TokenPairIssuer
    {
        return new TokenPairIssuer(new FakeTokenIssuer(), $this->refreshTokens, 900, 1_209_600);
    }

    private function accountRestorer(): AccountRestorer
    {
        return new AccountRestorer($this->users, new InMemoryDealershipRepository(), $this->audit);
    }

    private function loginWithPassword(?ClientAuthenticator $clients = null): LoginWithPassword
    {
        return new LoginWithPassword(
            $clients ?? $this->clients($this->webClient),
            $this->users,
            $this->tokenPairs(),
            $this->accountRestorer(),
            $this->audit,
        );
    }

    private function refreshAccessToken(?ClientAuthenticator $clients = null): RefreshAccessToken
    {
        return new RefreshAccessToken(
            $clients ?? $this->clients($this->webClient),
            $this->refreshTokens,
            $this->users,
            new FakeTokenIssuer(),
            $this->audit,
            900,
            1_209_600,
        );
    }

    private function loginWithGoogle(?ClientAuthenticator $clients = null): LoginWithGoogle
    {
        return new LoginWithGoogle(
            $clients ?? $this->clients($this->webClient),
            $this->googleVerifier,
            $this->identities,
            $this->users,
            $this->tokenPairs(),
            $this->accountRestorer(),
            $this->audit,
        );
    }

    private function issueServiceToken(ClientAuthenticator $clients): IssueServiceToken
    {
        return new IssueServiceToken($clients, new FakeTokenIssuer(), $this->audit, 900);
    }

    private function logout(): Logout
    {
        return new Logout($this->refreshTokens);
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

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email->value === $email->value) {
                return $user;
            }
        }

        return null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) instanceof \App\Domain\User\User;
    }

    public function insert(User $user): void
    {
        $this->byId[$user->id] = $user;
    }

    public function update(User $user): void
    {
        $this->byId[$user->id] = $user;
    }


    public function trash(string $id): void
    {
        if (($user = $this->byId[$id] ?? null) instanceof User) {
            $this->byId[$id] = $this->withStatus($user, TrashableStatus::Trashed, new \DateTimeImmutable());
        }
    }

    public function restore(string $id): void
    {
        if (($user = $this->byId[$id] ?? null) instanceof User) {
            $this->byId[$id] = $this->withStatus($user, TrashableStatus::Active, null);
        }
    }

    public function findTrashed(): array
    {
        return array_values(array_filter($this->byId, static fn (User $user): bool => $user->trash->isTrashed()));
    }

    public function purge(Trashable $entity): void
    {
        unset($this->byId[$entity->id]);
    }

    private function withStatus(User $user, TrashableStatus $status, ?\DateTimeImmutable $deletedAt): User
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
            trash: new TrashState($status, $deletedAt, $user->trash->anonymizedAt),
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

/** Só honra o contrato da porta, sem codificação JWT real -- o caso de uso não precisa de mais que isso. */
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

/** Devolve $nextClaims sem verificar assinatura nenhuma -- o caso de uso não precisa de mais que isso pra testar o fluxo de login. */
final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public ?GoogleIdentityClaims $nextClaims = null;

    public function verify(string $idToken): GoogleIdentityClaims
    {
        return $this->nextClaims ?? throw new DomainException('Invalid Google credential.', DomainErrorType::Unauthorized);
    }
}

/** O restore só chama restoreAutoTrashedOwnedBy() no caminho de restore -- os testes daqui não afirmam nada sobre concessionária, só precisam do contrato satisfeito. */
final class InMemoryDealershipRepository implements DealershipRepository
{
    public function findById(string $id): ?Dealership
    {
        return null;
    }

    public function findBySlug(string $slug): ?Dealership
    {
        return null;
    }

    public function insert(Dealership $dealership): void
    {
    }

    public function update(Dealership $dealership): void
    {
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        return [];
    }

    public function countByOwner(string $ownerUserId): int
    {
        return 0;
    }

    public function findPage(int $limit, int $offset): array
    {
        return [];
    }

    public function count(): int
    {
        return 0;
    }

    public function trash(string $id, bool $byOwnerDeactivation): void
    {
    }

    public function restore(string $id): void
    {
    }

    public function findTrashed(): array
    {
        return [];
    }

    public function purge(Trashable $entity): void
    {
    }

    public function trashAllOwnedBy(string $ownerUserId): void
    {
    }

    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void
    {
    }
}
