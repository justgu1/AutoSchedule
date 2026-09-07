<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Decodifica o access token uma vez só; antes disso o rate limit e o contexto de RLS decodificavam cada um o seu.
 * Token inválido não lança aqui: quem lança é o `AuthContextMiddleware`, depois do rate limit já ter contado a tentativa.
 */
final readonly class AuthenticateMiddleware implements Middleware
{
    public function __construct(private TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return $next($request);
        }

        try {
            return $next($request->withAttribute('auth', $this->tokens->decodeAccessToken($token)));
        } catch (\Throwable $exception) {
            return $next($request->withAttribute('auth_error', $exception));
        }
    }
}
