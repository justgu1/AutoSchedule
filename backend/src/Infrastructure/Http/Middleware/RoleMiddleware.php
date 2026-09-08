<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

final readonly class RoleMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $roles = $request->route()?->requiredRoles() ?? [];

        if ($roles === []) {
            return $next($request);
        }

        $claims = $request->attribute('auth');

        if (!$claims instanceof AccessTokenClaims) {
            throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
        }

        if (!in_array($claims->role, $roles, true)) {
            throw new DomainException('Not allowed for this role.', DomainErrorType::Forbidden);
        }

        return $next($request);
    }
}
