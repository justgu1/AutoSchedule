<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\OAuthService;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Notifications\Ports\MailProvider;
use App\Domain\Ports\DatabaseConnection;
use App\Domain\Users\Ports\UserRepository;
use App\Infrastructure\Audit\PostgresAuditLogger;
use App\Infrastructure\Auth\Google\GoogleJwksIdTokenVerifier;
use App\Infrastructure\Auth\Jwt\JwtTokenIssuer;
use App\Infrastructure\Auth\Postgres\PostgresOAuthClientRepository;
use App\Infrastructure\Auth\Postgres\PostgresPasswordResetTokenRepository;
use App\Infrastructure\Auth\Postgres\PostgresRefreshTokenRepository;
use App\Infrastructure\Auth\Postgres\PostgresUserIdentityRepository;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Controllers\UserController;
use App\Infrastructure\Logging\Logger;
use App\Infrastructure\Mail\SymfonyMailProvider;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RedisRateLimiter;
use App\Infrastructure\Redis\RedisConnection;
use App\Infrastructure\Users\PostgresUserRepository;

/**
 * Monta o Container com todos os bindings da aplicação -- único lugar que
 * sabe COMO construir cada dependência. Separado de routes/api.php, que só
 * sabe QUAIS rotas existem.
 */
final class ContainerFactory
{
    public static function build(Application $app): Container
    {
        $container = new Container();

        // Conecta como autoschedule_app (não a role admin/superuser usada por
        // bin/migrate.php e bin/seed.php) -- é a role restrita (NOSUPERUSER
        // NOBYPASSRLS) que faz o RLS de users valer a pena em runtime.
        $container->set(DatabaseConnection::class, static function () use ($app): DatabaseConnection {
            $config = $app->config('database');

            return new PostgresConnection(
                driver: $config['driver'],
                host: $config['host'],
                port: $config['port'],
                database: $config['database'],
                username: $config['app_username'],
                password: $config['app_password'],
            );
        });

        $container->set(TokenIssuer::class, static function () use ($app): TokenIssuer {
            $jwt = $app->config('auth')['jwt'];

            return new JwtTokenIssuer(
                privateKeyPem: file_get_contents($jwt['private_key_path']),
                publicKeyPem: file_get_contents($jwt['public_key_path']),
                issuer: $jwt['issuer'],
                audience: $jwt['audience'],
            );
        });

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
            clientId: $app->config('google')['client_id'],
            redis: $c->get(RedisConnection::class),
        ));
        $container->set(MailProvider::class, static function () use ($app): MailProvider {
            $mail = $app->config('mail');

            return new SymfonyMailProvider($mail['dsn'], $mail['from']);
        });
        $container->set(RedisConnection::class, static function () use ($app): RedisConnection {
            $config = $app->config('redis');

            return new RedisConnection(
                host: $config['host'],
                port: $config['port'],
                prefix: $config['prefix'],
                username: $config['username'],
                password: $config['password'],
            );
        });
        $container->set(
            RateLimiter::class,
            static fn (Container $c): RateLimiter => new RedisRateLimiter($c->get(RedisConnection::class)),
        );
        $container->set(PaginationPolicy::class, static function () use ($app): PaginationPolicy {
            $config = $app->config('pagination');

            return new PaginationPolicy($config['default_per_page'], $config['max_per_page']);
        });

        $container->set(OAuthService::class, static function (Container $c) use ($app): OAuthService {
            $auth = $app->config('auth');

            return new OAuthService(
                clients: $c->get(OAuthClientRepository::class),
                users: $c->get(UserRepository::class),
                refreshTokens: $c->get(RefreshTokenRepository::class),
                identities: $c->get(UserIdentityRepository::class),
                tokens: $c->get(TokenIssuer::class),
                googleVerifier: $c->get(GoogleIdTokenVerifier::class),
                audit: $c->get(AuditLogger::class),
                accessTokenTtl: $auth['access_token_ttl'],
                refreshTokenTtl: $auth['refresh_token_ttl'],
            );
        });
        $container->set(OAuthController::class, static fn (Container $c): OAuthController => new OAuthController(
            oauth: $c->get(OAuthService::class),
            refreshTokenTtl: $app->config('auth')['refresh_token_ttl'],
            cookieSecure: $app->config('security')['cookie_secure'],
        ));
        $container->set(UserController::class, static function (Container $c) use ($app): UserController {
            $mail = $app->config('mail');

            return new UserController(
                users: $c->get(UserRepository::class),
                refreshTokens: $c->get(RefreshTokenRepository::class),
                audit: $c->get(AuditLogger::class),
                pagination: $c->get(PaginationPolicy::class),
                passwordResetTokens: $c->get(PasswordResetTokenRepository::class),
                mail: $c->get(MailProvider::class),
                passwordResetTtl: $app->config('auth')['password_reset_ttl'],
                frontendUrl: $mail['frontend_url'],
                passwordResetTemplatePath: dirname(__DIR__, 2) . '/resources/mail/password-reset.html',
            );
        });

        return $container;
    }
}
