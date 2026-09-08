<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Cookie;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/** Só vale pra autenticação por cookie: quem manda o Bearer na mão não depende de credencial ambiente. */
final readonly class CsrfMiddleware implements Middleware
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private bool $cookieSecure = false)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $usingCookieAuth = $request->usesCookieAuth();

        if ($usingCookieAuth && !in_array($request->method(), self::SAFE_METHODS, true)) {
            $cookie = $request->cookie(Cookie::CSRF);
            $header = $request->header('x-csrf-token');

            if ($cookie === null || $header === null || !hash_equals($cookie, $header)) {
                throw new DomainException('Invalid or missing CSRF token.', DomainErrorType::Forbidden);
            }
        }

        $response = $next($request);

        // Sem o cookie ainda (primeira visita, ou acabou de logar nesse mesmo
        // request) -- emite um novo, pronto pra próxima mutação já ter o quê comparar.
        if ($request->cookie(Cookie::CSRF) === null) {
            return $response->withCookie(
                Cookie::CSRF,
                bin2hex(random_bytes(32)),
                httpOnly: false,
                secure: $this->cookieSecure,
            );
        }

        return $response;
    }
}
