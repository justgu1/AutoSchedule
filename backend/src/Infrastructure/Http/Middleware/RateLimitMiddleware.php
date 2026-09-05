<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RateLimitPolicy;
use Psr\Log\LoggerInterface;

/**
 * Roda antes de qualquer outro middleware (inclusive AuthContextMiddleware) --
 * tráfego abusivo é barrado com um único round-trip ao Redis, antes de abrir
 * transação ou tocar no Postgres. Cabeçalhos seguem o rascunho IETF de
 * RateLimit Header Fields (mesmo formato que a Cloudflare adota hoje).
 */
final class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Router $router,
        private readonly RateLimitPolicy $defaultPolicy,
        private readonly TokenIssuer $tokens,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $policy = $this->router->rateLimitPolicy($request->method(), $request->path()) ?? $this->defaultPolicy;

        try {
            $result = $this->limiter->attempt($policy->name . ':' . $this->identify($request), $policy);
        } catch (\Throwable $exception) {
            // Fail-open: Redis fora do ar não pode derrubar a API inteira, só
            // perde a proteção de rate limit enquanto isso.
            $this->logger->error((string) $exception);

            return $next($request);
        }

        $rateLimitHeader = "\"{$policy->name}\";r={$result->remaining};t={$result->resetSeconds}";
        $policyHeader = "\"{$policy->name}\";q={$policy->maxAttempts};w={$policy->windowSeconds}";

        if (!$result->allowed) {
            return Response::error('Too Many Requests.', 429)
                ->withHeader('Retry-After', (string) $result->resetSeconds)
                ->withHeader('RateLimit', $rateLimitHeader)
                ->withHeader('RateLimit-Policy', $policyHeader);
        }

        return $next($request)
            ->withHeader('RateLimit', $rateLimitHeader)
            ->withHeader('RateLimit-Policy', $policyHeader);
    }

    /** Por usuário quando o Bearer decodifica (mesmo IP, contas diferentes não competem pela mesma cota); por IP caso contrário. */
    private function identify(Request $request): string
    {
        $header = $request->header('authorization');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            try {
                return 'user:' . $this->tokens->decodeAccessToken(substr($header, 7))->subject;
            } catch (\Throwable) {
                // Token inválido -- a validação de verdade é do AuthContextMiddleware,
                // aqui só cai pro IP como qualquer request sem Bearer.
            }
        }

        return 'ip:' . $request->ip();
    }
}
