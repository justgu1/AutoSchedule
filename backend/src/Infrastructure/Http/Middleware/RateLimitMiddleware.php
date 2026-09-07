<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RateLimitPolicy;
use Psr\Log\LoggerInterface;

/**
 * Vem antes da autenticação no pipeline: tráfego abusivo custa um round-trip ao Redis, não uma transação.
 * Os cabeçalhos seguem o rascunho IETF de RateLimit Header Fields.
 */
final readonly class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private RateLimiter $limiter,
        private RateLimitPolicy $defaultPolicy,
        private RateLimitPolicy $authPolicy,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $policy = $request->route()?->rateLimitPolicy() === 'auth' ? $this->authPolicy : $this->defaultPolicy;

        try {
            $result = $this->limiter->attempt($policy->name . ':' . $this->bucketFor($request), $policy);
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

    /** Por usuário quando há token válido, pra contas diferentes no mesmo IP não competirem pela mesma cota. */
    private function bucketFor(Request $request): string
    {
        $claims = $request->attribute('auth');

        return $claims instanceof AccessTokenClaims ? 'user:' . $claims->subject : 'ip:' . $request->ip();
    }
}
