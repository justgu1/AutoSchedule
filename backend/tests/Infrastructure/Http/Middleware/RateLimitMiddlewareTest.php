<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Users\UserRole;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\RateLimitMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RateLimitPolicy;
use App\Infrastructure\RateLimit\RateLimitResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class RateLimitMiddlewareTest extends TestCase
{
    #[Test]
    public function permite_e_decora_a_resposta_com_os_headers_de_rate_limit(): void
    {
        $middleware = new RateLimitMiddleware(
            new FakeRateLimiter(new RateLimitResult(allowed: true, remaining: 4, resetSeconds: 30)),
            new Router(),
            new RateLimitPolicy('general', 5, 60),
            new FakeTokenIssuer(),
            new NullLogger(),
        );

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame(200, $response->status());
        $this->assertSame('"general";r=4;t=30', $response->headers()['RateLimit']);
        $this->assertSame('"general";q=5;w=60', $response->headers()['RateLimit-Policy']);
    }

    #[Test]
    public function barra_com_429_e_retry_after_quando_a_cota_estourou(): void
    {
        $middleware = new RateLimitMiddleware(
            new FakeRateLimiter(new RateLimitResult(allowed: false, remaining: 0, resetSeconds: 12)),
            new Router(),
            new RateLimitPolicy('general', 5, 60),
            new FakeTokenIssuer(),
            new NullLogger(),
        );
        $nextCalled = false;

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static function (Request $request) use (&$nextCalled): Response {
                $nextCalled = true;

                return new JsonResponse([]);
            },
        );

        $this->assertSame(429, $response->status());
        $this->assertSame('12', $response->headers()['Retry-After']);
        $this->assertFalse($nextCalled);
    }

    #[Test]
    public function usa_a_policy_da_rota_quando_declarada_em_vez_da_geral(): void
    {
        $router = new Router();
        $authPolicy = new RateLimitPolicy('auth', 5, 60);
        $router->post('/api/oauth/token', static fn (Request $request): Response => new JsonResponse([]), rateLimit: $authPolicy);
        $limiter = new FakeRateLimiter(new RateLimitResult(allowed: true, remaining: 4, resetSeconds: 60));
        $middleware = new RateLimitMiddleware($limiter, $router, new RateLimitPolicy('general', 1000, 60), new FakeTokenIssuer(), new NullLogger());

        $middleware->handle(
            new Request(method: 'POST', path: '/api/oauth/token'),
            static fn (Request $request): Response => new JsonResponse([]),
        );

        $this->assertSame($authPolicy, $limiter->lastPolicy);
    }

    #[Test]
    public function chave_usa_o_id_do_usuario_quando_o_bearer_decodifica(): void
    {
        $claims = AccessTokenClaims::issue('11111111-1111-4111-8111-111111111111', 'autoschedule-web', UserRole::Customer, [], 900);
        $limiter = new FakeRateLimiter(new RateLimitResult(allowed: true, remaining: 999, resetSeconds: 60));
        $middleware = new RateLimitMiddleware(
            $limiter,
            new Router(),
            new RateLimitPolicy('general', 1000, 60),
            new FakeTokenIssuer(['valid-token' => $claims]),
            new NullLogger(),
        );

        $middleware->handle(
            new Request(method: 'GET', path: '/api/me', headers: ['authorization' => 'Bearer valid-token']),
            static fn (Request $request): Response => new JsonResponse([]),
        );

        $this->assertSame('general:user:11111111-1111-4111-8111-111111111111', $limiter->lastKey);
    }

    #[Test]
    public function chave_cai_pro_ip_quando_nao_ha_bearer(): void
    {
        $limiter = new FakeRateLimiter(new RateLimitResult(allowed: true, remaining: 999, resetSeconds: 60));
        $middleware = new RateLimitMiddleware($limiter, new Router(), new RateLimitPolicy('general', 1000, 60), new FakeTokenIssuer(), new NullLogger());

        $middleware->handle(
            new Request(method: 'GET', path: '/api', ip: '203.0.113.9'),
            static fn (Request $request): Response => new JsonResponse([]),
        );

        $this->assertSame('general:ip:203.0.113.9', $limiter->lastKey);
    }

    #[Test]
    public function falha_aberta_quando_o_limiter_lanca_excecao(): void
    {
        $middleware = new RateLimitMiddleware(new BrokenRateLimiter(), new Router(), new RateLimitPolicy('general', 5, 60), new FakeTokenIssuer(), new NullLogger());
        $nextCalled = false;

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static function (Request $request) use (&$nextCalled): Response {
                $nextCalled = true;

                return new JsonResponse(['ok' => true]);
            },
        );

        $this->assertSame(200, $response->status());
        $this->assertTrue($nextCalled);
    }
}

final class FakeRateLimiter implements RateLimiter
{
    public ?string $lastKey = null;
    public ?RateLimitPolicy $lastPolicy = null;

    public function __construct(private readonly RateLimitResult $result)
    {
    }

    public function attempt(string $key, RateLimitPolicy $policy): RateLimitResult
    {
        $this->lastKey = $key;
        $this->lastPolicy = $policy;

        return $this->result;
    }
}

final class BrokenRateLimiter implements RateLimiter
{
    public function attempt(string $key, RateLimitPolicy $policy): RateLimitResult
    {
        throw new \RuntimeException('Redis indisponível.');
    }
}

final class NullLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
    }
}
