<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Http\Middleware\AuthContextMiddleware;
use App\Infrastructure\Http\Middleware\AuthenticateMiddleware;
use App\Infrastructure\Http\Middleware\CorsMiddleware;
use App\Infrastructure\Http\Middleware\CsrfMiddleware;
use App\Infrastructure\Http\Middleware\LoggingMiddleware;
use App\Infrastructure\Http\Middleware\RateLimitMiddleware;
use App\Infrastructure\Http\Middleware\ResolveRouteMiddleware;
use App\Infrastructure\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RateLimitPolicy;
use Psr\Log\LoggerInterface;

final class PipelineFactory
{
    /** A ordem é o contrato: rota resolvida antes de quem depende dela, e cota contada antes de tocar no Postgres. */
    public static function build(Container $container, Config $config): Pipeline
    {
        $logger = $container->get(LoggerInterface::class);

        return new Pipeline([
            new LoggingMiddleware(log: static function (string $line) use ($logger): void {
                $logger->info($line);
            }),
            new SecurityHeadersMiddleware($config->bool('security.hsts_enabled')),
            new CorsMiddleware($config->stringList('cors.allowed_origins')),
            $container->get(ResolveRouteMiddleware::class),
            $container->get(AuthenticateMiddleware::class),
            new RateLimitMiddleware(
                $container->get(RateLimiter::class),
                self::policy($config, 'general'),
                self::policy($config, 'auth'),
                $logger,
            ),
            new CsrfMiddleware($config->bool('security.cookie_secure')),
            $container->get(AuthContextMiddleware::class),
            new RoleMiddleware(),
        ]);
    }

    private static function policy(Config $config, string $name): RateLimitPolicy
    {
        return new RateLimitPolicy(
            $name,
            $config->int("rate_limit.{$name}.max_attempts"),
            $config->int("rate_limit.{$name}.window_seconds"),
        );
    }
}
