<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Preflight (`OPTIONS`) responde aqui mesmo, antes de qualquer outro
 * middleware -- não pode ser barrado por rate limit nem chegar no router.
 * `Access-Control-Allow-Credentials: true` porque o token agora vai em
 * cookie; por isso a origem nunca pode ser `*` (o próprio spec de CORS proíbe
 * as duas coisas juntas), só a allowlist exata.
 */
final class CorsMiddleware implements Middleware
{
    /** @param list<string> $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('origin');
        $allowed = $origin !== null && in_array($origin, $this->allowedOrigins, true);

        if ($request->method() === 'OPTIONS') {
            // 204 não pode ter corpo -- Response (não JsonResponse) fica vazio.
            $response = new Response(status: 204);

            return $allowed ? $this->withCorsHeaders($response, $origin) : $response;
        }

        $response = $next($request);

        return $allowed ? $this->withCorsHeaders($response, $origin) : $response;
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Max-Age', '600');
    }
}
