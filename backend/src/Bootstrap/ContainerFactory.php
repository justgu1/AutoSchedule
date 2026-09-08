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

/**
 * Composition root: liga cada port à implementação concreta e monta o que
 * depende de config. Separado de routes/api.php, que só sabe QUAIS rotas
 * existem.
 *
 * O que NÃO aparece aqui é resolvido por autowiring (`Container::autowire`):
 * caso de uso e controller cujas dependências são todas ports/classes já
 * registrados abaixo não precisam de binding próprio -- o construtor deles já
 * diz tudo. Só entra nesta lista quem depende de config, de escalar, ou de uma
 * escolha que o typehint sozinho não resolve.
 */
final class ContainerFactory
{
    public static function build(Config $app): Container
    {
        $container = new Container();

        // Conecta como autoschedule_app (não a role admin/superuser usada por
        // bin/migrate.php e bin/seed.php) -- é a role restrita (NOSUPERUSER
        // NOBYPASSRLS) que faz o RLS de users valer a pena em runtime.
        $container->set(DatabaseConnection::class, static fn (): DatabaseConnection => new PostgresConnection(
            driver: $app->string('database.driver'),
            host: $app->string('database.host'),
            port: $app->int('database.port'),
            database: $app->string('database.database'),
            username: $app->string('database.app_username'),
            password: $app->string('database.app_password'),
        ));

        $container->set(TokenIssuer::class, static fn (): TokenIssuer => new JwtTokenIssuer(
            privateKeyPem: self::readKey($app->string('auth.jwt.private_key_path')),
            publicKeyPem: self::readKey($app->string('auth.jwt.public_key_path')),
            issuer: $app->string('auth.jwt.issuer'),
            audience: $app->string('auth.jwt.audience'),
        ));

        $container->set(
            UserRepository::class,
            static fn (Container $c): UserRepository => new PostgresUserRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(
            OAuthClientRepository::class,
            static fn (Container $c): OAuthClientRepository => new PostgresOAuthClientRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(
            RefreshTokenRepository::class,
            static fn (Container $c): RefreshTokenRepository => new PostgresRefreshTokenRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(
            AuditLogger::class,
            static fn (Container $c): AuditLogger => new PostgresAuditLogger($c->get(DatabaseConnection::class)->pdo(), new Logger()),
        );
        $container->set(
            PasswordResetTokenRepository::class,
            static fn (Container $c): PasswordResetTokenRepository => new PostgresPasswordResetTokenRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(
            UserIdentityRepository::class,
            static fn (Container $c): UserIdentityRepository => new PostgresUserIdentityRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(GoogleIdTokenVerifier::class, static fn (Container $c): GoogleIdTokenVerifier => new GoogleJwksIdTokenVerifier(
            clientId: $app->string('google.client_id'),
            redis: $c->get(RedisConnection::class),
        ));
        $container->set(MailProvider::class, static fn (): MailProvider => new SymfonyMailProvider($app->string('mail.dsn'), $app->string('mail.from')));
        $container->set(MailTemplateRenderer::class, static fn (): MailTemplateRenderer => new MailTemplate());
        $container->set(StorageProvider::class, static fn (): StorageProvider => new MinioAdapter(
            endpoint: $app->string('storage.endpoint'),
            bucket: $app->string('storage.bucket'),
            region: $app->string('storage.region'),
            accessKey: $app->string('storage.access_key'),
            secretKey: $app->string('storage.secret_key'),
            publicUrl: $app->string('storage.public_url'),
        ));
        $container->set(
            FileRepository::class,
            static fn (Container $c): FileRepository => new PostgresFileRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(
            ImageOptimizer::class,
            static fn (): ImageOptimizer => new GdImageOptimizer($app->string('storage.temp_path')),
        );
        $container->set(
            TempFileStore::class,
            static fn (): TempFileStore => new LocalTempFileStore($app->string('storage.temp_path')),
        );
        $container->set(
            DealershipRepository::class,
            static fn (Container $c): DealershipRepository => new PostgresDealershipRepository($c->get(DatabaseConnection::class)->pdo()),
        );
        $container->set(RedisConnection::class, static fn (): RedisConnection => new RedisConnection(
            host: $app->string('redis.host'),
            port: $app->int('redis.port'),
            prefix: $app->string('redis.prefix'),
            username: $app->stringOrNull('redis.username'),
            password: $app->stringOrNull('redis.password'),
        ));
        $container->set(
            RateLimiter::class,
            static fn (Container $c): RateLimiter => new RedisRateLimiter($c->get(RedisConnection::class)),
        );
        $container->set(
            RedisQueue::class,
            static fn (Container $c): RedisQueue => new RedisQueue($c->get(RedisConnection::class)),
        );
        // Alias pro port -- mesmo singleton de RedisQueue::class.
        $container->set(Queue::class, static fn (Container $c): Queue => $c->get(RedisQueue::class));
        $container->set(
            JobStatusStore::class,
            static fn (Container $c): JobStatusStore => new JobStatusStore($c->get(RedisConnection::class)),
        );
        $container->set(JobProgress::class, static fn (Container $c): JobProgress => $c->get(JobStatusStore::class));
        // Cada domínio com lixeira reversível registra aqui sua própria purga,
        // reaproveitando a mesma ScheduledTask genérica (App\Infrastructure\Scheduler\PurgeTrashedEntitiesTask).
        $container->set(Scheduler::class, static function (Container $c): Scheduler {
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
        $container->set(PaginationPolicy::class, static fn (): PaginationPolicy => new PaginationPolicy(
            $app->int('pagination.default_per_page'),
            $app->int('pagination.max_per_page'),
        ));

        // Casos de uso que dependem de config -- o resto o autowire resolve.
        $container->set(TokenPairIssuer::class, static fn (Container $c): TokenPairIssuer => new TokenPairIssuer(
            tokens: $c->get(TokenIssuer::class),
            refreshTokens: $c->get(RefreshTokenRepository::class),
            accessTokenTtl: $app->int('auth.access_token_ttl'),
            refreshTokenTtl: $app->int('auth.refresh_token_ttl'),
        ));
        $container->set(RefreshAccessToken::class, static fn (Container $c): RefreshAccessToken => new RefreshAccessToken(
            clients: $c->get(ClientAuthenticator::class),
            refreshTokens: $c->get(RefreshTokenRepository::class),
            users: $c->get(UserRepository::class),
            tokens: $c->get(TokenIssuer::class),
            audit: $c->get(AuditLogger::class),
            accessTokenTtl: $app->int('auth.access_token_ttl'),
            refreshTokenTtl: $app->int('auth.refresh_token_ttl'),
        ));
        $container->set(IssueServiceToken::class, static fn (Container $c): IssueServiceToken => new IssueServiceToken(
            clients: $c->get(ClientAuthenticator::class),
            tokens: $c->get(TokenIssuer::class),
            audit: $c->get(AuditLogger::class),
            accessTokenTtl: $app->int('auth.access_token_ttl'),
        ));
        $container->set(RequestPasswordReset::class, static fn (Container $c): RequestPasswordReset => new RequestPasswordReset(
            users: $c->get(UserRepository::class),
            passwordResetTokens: $c->get(PasswordResetTokenRepository::class),
            mailTemplates: $c->get(MailTemplateRenderer::class),
            queue: $c->get(Queue::class),
            passwordResetTtl: $app->int('auth.password_reset_ttl'),
            frontendUrl: $app->string('mail.frontend_url'),
            templatePath: dirname(__DIR__, 2) . '/resources/mail/password-reset.html',
        ));

        // O worker resolve o job pelo nome da classe que veio no envelope --
        // registrar explícito é o que garante que a fila não dependa de um
        // autowire que ninguém exercitou até o job falhar em produção.
        $container->set(SendEmailJob::class, static fn (Container $c): SendEmailJob => new SendEmailJob($c->get(MailProvider::class)));

        $container->set(OAuthController::class, static fn (Container $c): OAuthController => new OAuthController(
            loginWithPassword: $c->get(LoginWithPassword::class),
            refreshAccessToken: $c->get(RefreshAccessToken::class),
            loginWithGoogle: $c->get(LoginWithGoogle::class),
            issueServiceToken: $c->get(IssueServiceToken::class),
            revokeSession: $c->get(Logout::class),
            refreshTokenTtl: $app->int('auth.refresh_token_ttl'),
            cookieSecure: $app->bool('security.cookie_secure'),
        ));

        $container->set(ZipCodeCacheRepository::class, static fn (Container $c): ZipCodeCacheRepository => new PostgresZipCodeCacheRepository($c->get(DatabaseConnection::class)));
        $container->set(ZipCodeProvider::class, static fn (): ZipCodeProvider => new ViaCepZipCodeProvider());

        return $container;
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
