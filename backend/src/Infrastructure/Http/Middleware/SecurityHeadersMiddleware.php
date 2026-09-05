<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

final class SecurityHeadersMiddleware implements Middleware
{
    public function __construct(private readonly bool $hstsEnabled = false)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Content-Security-Policy', "default-src 'none'")
            ->withHeader('Cache-Control', 'no-store');

        // Atrás de env porque não tem TLS no ambiente local -- HSTS com TLS
        // desligado quebra o acesso via http:// (browser passa a exigir https).
        if ($this->hstsEnabled) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
