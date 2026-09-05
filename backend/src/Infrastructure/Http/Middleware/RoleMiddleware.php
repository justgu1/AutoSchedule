<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

final readonly class RoleMiddleware implements Middleware
{
    public function __construct(private Router $router)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $roles = $this->router->requiredRoles($request->method(), $request->path());

        if ($roles === []) {
            return $next($request);
        }

        $claims = $request->attribute('auth');

        if ($claims === null) {
            throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
        }

        if (!in_array($claims->role?->value, $roles, true)) {
            throw new DomainException('Not allowed for this role.', DomainErrorType::Forbidden);
        }

        return $next($request);
    }
}
