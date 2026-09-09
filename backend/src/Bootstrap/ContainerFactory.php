<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Appointment\CreateAppointment;
use App\Application\Appointment\NotifyAppointmentStatusChanged;
use App\Application\Appointment\ReleaseAppointment;
use App\Application\Auth\IssueServiceToken;
use App\Application\Auth\LoginWithGoogle;
use App\Application\Auth\LoginWithPassword;
use App\Application\Auth\Logout;
use App\Application\Auth\RefreshAccessToken;
use App\Application\Auth\TokenTtl;
use App\Application\Availability\ListAvailableSlots;
use App\Application\Dealership\DealershipFinder;
use App\Application\Notification\SendEmail;
use App\Application\Ports\JobProgress;
use App\Application\Ports\MailTemplateRenderer;
use App\Application\Ports\Queue;
use App\Application\Ports\TempFileStore;
use App\Application\Ports\Transaction;
use App\Application\User\RequestPasswordReset;
use App\Application\Vehicle\VehicleFinder;
use App\Config;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;
use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\File\Ports\FileRepository;
use App\Domain\File\Ports\ImageOptimizer;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\Notification\Ports\MailProvider;
use App\Domain\User\Ports\UserRepository;
use App\Domain\Vehicle\Ports\VehicleAmenityCatalog;
use App\Domain\Vehicle\Ports\VehicleAmenityLinkRepository;
use App\Domain\Vehicle\Ports\VehicleImageRepository;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\ZipCode\Ports\ZipCodeCacheRepository;
use App\Domain\ZipCode\Ports\ZipCodeProvider;
use App\Infrastructure\Auth\Google\GoogleJwksIdTokenVerifier;
use App\Infrastructure\Auth\Jwt\JwtTokenIssuer;
use App\Infrastructure\Container\Container;
use App\Infrastructure\File\GdImageOptimizer;
use App\Infrastructure\File\LocalTempFileStore;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Jobs\JobStatusStore;
use App\Infrastructure\Logging\Logger;
use App\Infrastructure\Mail\MailTemplate;
use App\Infrastructure\Mail\SymfonyMailProvider;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Persistence\DatabaseConnection;
use App\Infrastructure\Persistence\PdoTransaction;
use App\Infrastructure\Persistence\PostgresAppointmentRepository;
use App\Infrastructure\Persistence\PostgresAuditLogger;
use App\Infrastructure\Persistence\PostgresAvailabilityExceptionRepository;
use App\Infrastructure\Persistence\PostgresConnection;
use App\Infrastructure\Persistence\PostgresDealershipAvailabilityRuleRepository;
use App\Infrastructure\Persistence\PostgresDealershipRepository;
use App\Infrastructure\Persistence\PostgresFileRepository;
use App\Infrastructure\Persistence\PostgresOAuthClientRepository;
use App\Infrastructure\Persistence\PostgresPasswordResetTokenRepository;
use App\Infrastructure\Persistence\PostgresRefreshTokenRepository;
use App\Infrastructure\Persistence\PostgresUserIdentityRepository;
use App\Infrastructure\Persistence\PostgresUserRepository;
use App\Infrastructure\Persistence\PostgresVehicleAmenityCatalog;
use App\Infrastructure\Persistence\PostgresVehicleAmenityLinkRepository;
use App\Infrastructure\Persistence\PostgresVehicleAvailabilityRuleRepository;
use App\Infrastructure\Persistence\PostgresVehicleImageRepository;
use App\Infrastructure\Persistence\PostgresVehicleRepository;
use App\Infrastructure\Persistence\PostgresZipCodeCacheRepository;
use App\Infrastructure\Queue\RedisQueue;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RedisRateLimiter;
use App\Infrastructure\Redis\RedisConnection;
use App\Infrastructure\Scheduler\AppointmentLifecycleSweepTask;
use App\Infrastructure\Scheduler\ExpirePendingAppointmentsTask;
use App\Infrastructure\Scheduler\PurgeTrashedEntitiesTask;
use App\Infrastructure\Scheduler\Scheduler;
use App\Infrastructure\Scheduler\SendAppointmentConfirmationEmailsTask;
use App\Infrastructure\Storage\MinioAdapter;
use App\Infrastructure\ZipCode\ViaCepZipCodeProvider;
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
        self::bindScheduler($container, $config);

        return $container;
    }

    private static function bindPorts(Container $container): void
    {
        $container->bind(UserRepository::class, PostgresUserRepository::class);
        $container->bind(DealershipRepository::class, PostgresDealershipRepository::class);
        $container->bind(VehicleRepository::class, PostgresVehicleRepository::class);
        $container->bind(VehicleImageRepository::class, PostgresVehicleImageRepository::class);
        $container->bind(VehicleAmenityCatalog::class, PostgresVehicleAmenityCatalog::class);
        $container->bind(VehicleAmenityLinkRepository::class, PostgresVehicleAmenityLinkRepository::class);
        $container->bind(DealershipAvailabilityRuleRepository::class, PostgresDealershipAvailabilityRuleRepository::class);
        $container->bind(VehicleAvailabilityRuleRepository::class, PostgresVehicleAvailabilityRuleRepository::class);
        $container->bind(AvailabilityExceptionRepository::class, PostgresAvailabilityExceptionRepository::class);
        $container->bind(AppointmentRepository::class, PostgresAppointmentRepository::class);
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
        $container->bind(Transaction::class, PdoTransaction::class);
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

        $container->singleton(Router::class, static fn (Container $c): Router => new Router($c));

        $container->singleton(ExceptionHandler::class, static fn (Container $c): ExceptionHandler => new ExceptionHandler(
            debug: $config->bool('debug'),
            logger: $c->get(LoggerInterface::class),
        ));

        $container->singleton(ImageOptimizer::class, static fn (): ImageOptimizer => new GdImageOptimizer($config->string('storage.temp_path')));
        $container->singleton(TempFileStore::class, static fn (): TempFileStore => new LocalTempFileStore($config->string('storage.temp_path')));

        $container->singleton(PaginationPolicy::class, static fn (): PaginationPolicy => new PaginationPolicy(
            $config->int('pagination.default_per_page'),
            $config->int('pagination.max_per_page'),
        ));

        $container->singleton(TokenTtl::class, static fn (): TokenTtl => new TokenTtl(
            accessSeconds: $config->int('auth.access_token_ttl'),
            refreshSeconds: $config->int('auth.refresh_token_ttl'),
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

        $container->singleton(NotifyAppointmentStatusChanged::class, static fn (Container $c): NotifyAppointmentStatusChanged => new NotifyAppointmentStatusChanged(
            queue: $c->get(Queue::class),
            mailTemplates: $c->get(MailTemplateRenderer::class),
            templatePath: dirname(__DIR__, 2) . '/resources/mail/appointment-status-changed.html',
        ));

        $container->singleton(CreateAppointment::class, static fn (Container $c): CreateAppointment => new CreateAppointment(
            vehicles: $c->get(VehicleFinder::class),
            dealerships: $c->get(DealershipFinder::class),
            users: $c->get(UserRepository::class),
            appointments: $c->get(AppointmentRepository::class),
            listAvailableSlots: $c->get(ListAvailableSlots::class),
            audit: $c->get(AuditLogger::class),
            transaction: $c->get(Transaction::class),
            queue: $c->get(Queue::class),
            mailTemplates: $c->get(MailTemplateRenderer::class),
            staffNotificationTemplatePath: dirname(__DIR__, 2) . '/resources/mail/appointment-created-staff.html',
        ));

        $container->singleton(OAuthController::class, static fn (Container $c): OAuthController => new OAuthController(
            loginWithPassword: $c->get(LoginWithPassword::class),
            refreshAccessToken: $c->get(RefreshAccessToken::class),
            loginWithGoogle: $c->get(LoginWithGoogle::class),
            issueServiceToken: $c->get(IssueServiceToken::class),
            revokeSession: $c->get(Logout::class),
            ttl: $c->get(TokenTtl::class),
            cookieSecure: $config->bool('security.cookie_secure'),
        ));

        // O worker resolve o job pelo nome que veio no envelope, então registrar explícito é o que
        // garante que a fila não dependa de um autowire nunca exercitado.
        $container->singleton(SendEmail::class, static fn (Container $c): SendEmail => new SendEmail($c->get(MailProvider::class)));
    }

    /** Cada domínio com lixeira reversível registra a própria purga sobre a mesma ScheduledTask. */
    private static function bindScheduler(Container $container, Config $config): void
    {
        $container->singleton(Scheduler::class, static fn (Container $c): Scheduler => new Scheduler(
            redis: $c->get(RedisConnection::class),
            tasks: [
                new PurgeTrashedEntitiesTask(
                    name: 'purge-trashed-users',
                    dueIntervalSeconds: 86400,
                    repository: $c->get(UserRepository::class),
                    audit: $c->get(AuditLogger::class),
                    event: AuditEvent::AccountPurged,
                    transaction: $c->get(Transaction::class),
                ),
                new PurgeTrashedEntitiesTask(
                    name: 'purge-trashed-dealerships',
                    dueIntervalSeconds: 86400,
                    repository: $c->get(DealershipRepository::class),
                    audit: $c->get(AuditLogger::class),
                    event: AuditEvent::DealershipPurged,
                    transaction: $c->get(Transaction::class),
                ),
                new PurgeTrashedEntitiesTask(
                    name: 'purge-trashed-vehicles',
                    dueIntervalSeconds: 86400,
                    repository: $c->get(VehicleRepository::class),
                    audit: $c->get(AuditLogger::class),
                    event: AuditEvent::VehiclePurged,
                    transaction: $c->get(Transaction::class),
                ),
                new SendAppointmentConfirmationEmailsTask(
                    appointments: $c->get(AppointmentRepository::class),
                    transaction: $c->get(Transaction::class),
                    queue: $c->get(Queue::class),
                    mailTemplates: $c->get(MailTemplateRenderer::class),
                    templatePath: dirname(__DIR__, 2) . '/resources/mail/appointment-confirmation-request.html',
                    frontendUrl: $config->string('mail.frontend_url'),
                    pendingTtlSeconds: $config->int('appointments.pending_ttl_seconds'),
                ),
                new ExpirePendingAppointmentsTask(
                    appointments: $c->get(AppointmentRepository::class),
                    transaction: $c->get(Transaction::class),
                    audit: $c->get(AuditLogger::class),
                    notify: $c->get(NotifyAppointmentStatusChanged::class),
                ),
                new AppointmentLifecycleSweepTask(
                    appointments: $c->get(AppointmentRepository::class),
                    release: $c->get(ReleaseAppointment::class),
                    transaction: $c->get(Transaction::class),
                ),
            ],
        ));
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
