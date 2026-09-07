<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

/** Casa a rota uma vez e anexa ao request: antes disso, cada middleware e o dispatch casavam por conta própria. */
final readonly class ResolveRouteMiddleware implements Middleware
{
    public function __construct(private Router $router)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        return $next($request->withAttribute('route', $this->router->match($request->method(), $request->path())));
    }
}
