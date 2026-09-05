<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\OAuthService;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Ports\DatabaseConnection;
use App\Domain\Users\Ports\UserRepository;
use App\Infrastructure\Audit\PostgresAuditLogger;
use App\Infrastructure\Auth\Jwt\JwtTokenIssuer;
use App\Infrastructure\Auth\Postgres\PostgresOAuthClientRepository;
use App\Infrastructure\Auth\Postgres\PostgresRefreshTokenRepository;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Logging\Logger;
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

        $container->set(OAuthService::class, static function (Container $c) use ($app): OAuthService {
            $auth = $app->config('auth');

            return new OAuthService(
                clients: $c->get(OAuthClientRepository::class),
                users: $c->get(UserRepository::class),
                refreshTokens: $c->get(RefreshTokenRepository::class),
                tokens: $c->get(TokenIssuer::class),
                audit: $c->get(AuditLogger::class),
                accessTokenTtl: $auth['access_token_ttl'],
                refreshTokenTtl: $auth['refresh_token_ttl'],
            );
        });

        return $container;
    }
}
