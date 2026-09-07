<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Auth\ClientAuthenticator;
use App\Application\Auth\IssueServiceToken;
use App\Application\Auth\LoginWithGoogle;
use App\Application\Auth\LoginWithPassword;
use App\Application\Auth\Logout;
use App\Application\Auth\RefreshAccessToken;
use App\Application\Auth\TokenPairIssuer;
use App\Application\Notification\SendEmailJob;
use App\Application\Ports\JobProgress;
use App\Application\Ports\MailTemplateRenderer;
use App\Application\Ports\Queue;
use App\Application\Ports\TempFileStore;
use App\Application\User\RequestPasswordReset;
use App\Config;
use App\Domain\Address\Ports\ZipCodeCacheRepository;
use App\Domain\Address\Ports\ZipCodeProvider;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\File\Ports\FileRepository;
use App\Domain\File\Ports\ImageOptimizer;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\Notification\Ports\MailProvider;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Infrastructure\Address\PostgresZipCodeCacheRepository;
use App\Infrastructure\Address\ViaCepZipCodeProvider;
use App\Infrastructure\Audit\PostgresAuditLogger;
use App\Infrastructure\Auth\Google\GoogleJwksIdTokenVerifier;
use App\Infrastructure\Auth\Jwt\JwtTokenIssuer;
use App\Infrastructure\Auth\Postgres\PostgresOAuthClientRepository;
use App\Infrastructure\Auth\Postgres\PostgresPasswordResetTokenRepository;
use App\Infrastructure\Auth\Postgres\PostgresRefreshTokenRepository;
use App\Infrastructure\Auth\Postgres\PostgresUserIdentityRepository;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Dealership\PostgresDealershipRepository;
use App\Infrastructure\File\GdImageOptimizer;
use App\Infrastructure\File\LocalTempFileStore;
use App\Infrastructure\File\PostgresFileRepository;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Jobs\JobStatusStore;
use App\Infrastructure\Logging\Logger;
use App\Infrastructure\Mail\MailTemplate;
use App\Infrastructure\Mail\SymfonyMailProvider;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Queue\RedisQueue;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RedisRateLimiter;
use App\Infrastructure\Redis\RedisConnection;
use App\Infrastructure\Scheduler\PurgeTrashedEntitiesTask;
use App\Infrastructure\Scheduler\Scheduler;
use App\Infrastructure\Storage\MinioAdapter;
use App\Infrastructure\User\PostgresUserRepository;
use Psr\Log\LoggerInterface;

/**
 * Composition root. O que não aparece aqui é resolvido por autowiring: caso de uso e controller
 * cujas dependências já estão registradas abaixo não precisam de linha própria.
 */
final class ContainerFactory
{
    public static function build(Config $config): Container
    {
        $container = new Container();

        self::bindPorts($container);
        self::bindConfigured($container, $config);
        self::bindScheduler($container);

        return $container;
    }

    private static function bindPorts(Container $container): void
    {
        $container->bind(UserRepository::class, PostgresUserRepository::class);
        $container->bind(DealershipRepository::class, PostgresDealershipRepository::class);
        $container->bind(FileRepository::class, PostgresFileRepository::class);
        $container->bind(OAuthClientRepository::class, PostgresOAuthClientRepository::class);
        $container->bind(RefreshTokenRepository::class, PostgresRefreshTokenRepository::class);
        $container->bind(PasswordResetTokenRepository::class, PostgresPasswordResetTokenRepository::class);
        $container->bind(UserIdentityRepository::class, PostgresUserIdentityRepository::class);
        $container->bind(ZipCodeCacheRepository::class, PostgresZipCodeCacheRepository::class);
        $container->bind(ZipCodeProvider::class, ViaCepZipCodeProvider::class);
        $container->bind(AuditLogger::class, PostgresAuditLogger::class);
        $container->bind(LoggerInterface::class, Logger::class);
        $container->bind(MailTemplateRenderer::class, MailTemplate::class);
        $container->bind(RateLimiter::class, RedisRateLimiter::class);
        $container->bind(Queue::class, RedisQueue::class);
        $container->bind(JobProgress::class, JobStatusStore::class);
    }

    private static function bindConfigured(Container $container, Config $config): void
    {
        // Conecta como a role restrita (NOSUPERUSER NOBYPASSRLS), não a das migrations: é o que faz o RLS valer em runtime.
        $container->singleton(DatabaseConnection::class, static fn (): DatabaseConnection => new PostgresConnection(
            driver: $config->string('database.driver'),
            host: $config->string('database.host'),
            port: $config->int('database.port'),
            database: $config->string('database.database'),
            username: $config->string('database.app_username'),
            password: $config->string('database.app_password'),
        ));

        $container->singleton(RedisConnection::class, static fn (): RedisConnection => new RedisConnection(
            host: $config->string('redis.host'),
            port: $config->int('redis.port'),
            prefix: $config->string('redis.prefix'),
            username: $config->stringOrNull('redis.username'),
            password: $config->stringOrNull('redis.password'),
        ));

        $container->singleton(TokenIssuer::class, static fn (): TokenIssuer => new JwtTokenIssuer(
            privateKeyPem: self::readKey($config->string('auth.jwt.private_key_path')),
            publicKeyPem: self::readKey($config->string('auth.jwt.public_key_path')),
            issuer: $config->string('auth.jwt.issuer'),
            audience: $config->string('auth.jwt.audience'),
        ));

        $container->singleton(GoogleIdTokenVerifier::class, static fn (Container $c): GoogleIdTokenVerifier => new GoogleJwksIdTokenVerifier(
            clientId: $config->string('google.client_id'),
            redis: $c->get(RedisConnection::class),
        ));

        $container->singleton(StorageProvider::class, static fn (): StorageProvider => new MinioAdapter(
            endpoint: $config->string('storage.endpoint'),
            bucket: $config->string('storage.bucket'),
            region: $config->string('storage.region'),
            accessKey: $config->string('storage.access_key'),
            secretKey: $config->string('storage.secret_key'),
            publicUrl: $config->string('storage.public_url'),
        ));

        $container->singleton(MailProvider::class, static fn (): MailProvider => new SymfonyMailProvider(
            $config->string('mail.dsn'),
            $config->string('mail.from'),
        ));

        $container->singleton(ImageOptimizer::class, static fn (): ImageOptimizer => new GdImageOptimizer($config->string('storage.temp_path')));
        $container->singleton(TempFileStore::class, static fn (): TempFileStore => new LocalTempFileStore($config->string('storage.temp_path')));

        $container->singleton(PaginationPolicy::class, static fn (): PaginationPolicy => new PaginationPolicy(
            $config->int('pagination.default_per_page'),
            $config->int('pagination.max_per_page'),
        ));

        $container->singleton(TokenPairIssuer::class, static fn (Container $c): TokenPairIssuer => new TokenPairIssuer(
            tokens: $c->get(TokenIssuer::class),
            refreshTokens: $c->get(RefreshTokenRepository::class),
            accessTokenTtl: $config->int('auth.access_token_ttl'),
            refreshTokenTtl: $config->int('auth.refresh_token_ttl'),
        ));

        $container->singleton(RefreshAccessToken::class, static fn (Container $c): RefreshAccessToken => new RefreshAccessToken(
            clients: $c->get(ClientAuthenticator::class),
            refreshTokens: $c->get(RefreshTokenRepository::class),
            users: $c->get(UserRepository::class),
            tokens: $c->get(TokenIssuer::class),
            audit: $c->get(AuditLogger::class),
            accessTokenTtl: $config->int('auth.access_token_ttl'),
            refreshTokenTtl: $config->int('auth.refresh_token_ttl'),
        ));

        $container->singleton(IssueServiceToken::class, static fn (Container $c): IssueServiceToken => new IssueServiceToken(
            clients: $c->get(ClientAuthenticator::class),
            tokens: $c->get(TokenIssuer::class),
            audit: $c->get(AuditLogger::class),
            accessTokenTtl: $config->int('auth.access_token_ttl'),
        ));

        $container->singleton(RequestPasswordReset::class, static fn (Container $c): RequestPasswordReset => new RequestPasswordReset(
            users: $c->get(UserRepository::class),
            passwordResetTokens: $c->get(PasswordResetTokenRepository::class),
            mailTemplates: $c->get(MailTemplateRenderer::class),
            queue: $c->get(Queue::class),
            passwordResetTtl: $config->int('auth.password_reset_ttl'),
            frontendUrl: $config->string('mail.frontend_url'),
            templatePath: dirname(__DIR__, 2) . '/resources/mail/password-reset.html',
        ));

        $container->singleton(OAuthController::class, static fn (Container $c): OAuthController => new OAuthController(
            loginWithPassword: $c->get(LoginWithPassword::class),
            refreshAccessToken: $c->get(RefreshAccessToken::class),
            loginWithGoogle: $c->get(LoginWithGoogle::class),
            issueServiceToken: $c->get(IssueServiceToken::class),
            revokeSession: $c->get(Logout::class),
            refreshTokenTtl: $config->int('auth.refresh_token_ttl'),
            cookieSecure: $config->bool('security.cookie_secure'),
        ));

        // O worker resolve o job pelo nome que veio no envelope, então registrar explícito é o que
        // garante que a fila não dependa de um autowire nunca exercitado.
        $container->singleton(SendEmailJob::class, static fn (Container $c): SendEmailJob => new SendEmailJob($c->get(MailProvider::class)));
    }

    /** Cada domínio com lixeira reversível registra a própria purga sobre a mesma ScheduledTask. */
    private static function bindScheduler(Container $container): void
    {
        $container->singleton(Scheduler::class, static function (Container $c): Scheduler {
            $users = $c->get(UserRepository::class);
            $dealerships = $c->get(DealershipRepository::class);
            $audit = $c->get(AuditLogger::class);

            return new Scheduler(
                redis: $c->get(RedisConnection::class),
                tasks: [
                    new PurgeTrashedEntitiesTask(
                        name: 'purge-trashed-users',
                        graceDays: 30,
                        dueIntervalSeconds: 86400,
                        findEligible: $users->findPurgeEligible(...),
                        purge: static fn (User $user) => $users->anonymizeAndSoftDelete($user->id),
                        identify: static fn (User $user): string => $user->id,
                        audit: $audit,
                        event: AuditEvent::AccountPurged,
                        auditableType: 'User',
                    ),
                    new PurgeTrashedEntitiesTask(
                        name: 'purge-trashed-dealerships',
                        graceDays: 30,
                        dueIntervalSeconds: 86400,
                        findEligible: $dealerships->findPurgeEligible(...),
                        purge: static fn (Dealership $dealership) => $dealerships->update($dealership->anonymized()),
                        identify: static fn (Dealership $dealership): string => $dealership->id,
                        audit: $audit,
                        event: AuditEvent::DealershipPurged,
                        auditableType: 'Dealership',
                    ),
                ],
            );
        });
    }

    private static function readKey(string $path): string
    {
        $pem = file_get_contents($path);

        if ($pem === false) {
            throw new \RuntimeException(sprintf('Could not read the JWT key at "%s".', $path));
        }

        return $pem;
    }
}
