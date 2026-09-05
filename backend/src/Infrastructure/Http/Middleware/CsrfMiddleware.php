<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Double-submit cookie: `XSRF-TOKEN` (legível por JS, não HttpOnly) precisa
 * bater com o header `X-CSRF-Token` em toda mutação -- só quando a
 * autenticação veio do cookie `access_token` (sem header Authorization
 * explícito). Um cliente que manda `Authorization: Bearer` na mão (Postman,
 * script, outro serviço) não depende de credencial ambiente nenhuma, então
 * CSRF não se aplica a ele.
 */
final readonly class CsrfMiddleware implements Middleware
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private bool $cookieSecure = false)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $usingCookieAuth = $request->header('authorization') === null && $request->cookie('access_token') !== null;

        if ($usingCookieAuth && !in_array($request->method(), self::SAFE_METHODS, true)) {
            $cookie = $request->cookie('XSRF-TOKEN');
            $header = $request->header('x-csrf-token');

            if ($cookie === null || $header === null || !hash_equals($cookie, $header)) {
                throw new DomainException('Invalid or missing CSRF token.', DomainErrorType::Forbidden);
            }
        }

        $response = $next($request);

        // Sem o cookie ainda (primeira visita, ou acabou de logar nesse mesmo
        // request) -- emite um novo, pronto pra próxima mutação já ter o quê comparar.
        if ($request->cookie('XSRF-TOKEN') === null) {
            return $response->withCookie(
                'XSRF-TOKEN',
                bin2hex(random_bytes(32)),
                httpOnly: false,
                secure: $this->cookieSecure,
            );
        }

        return $response;
    }
}
