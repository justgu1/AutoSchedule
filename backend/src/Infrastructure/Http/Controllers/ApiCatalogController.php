<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\User\DTO\UserProfile;
use App\Application\User\UserFinder;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

/** Lista só o que o role de quem pergunta alcança, por isso `roles` não vai na resposta. */
final readonly class ApiCatalogController
{
    public function __construct(
        private Router $router,
        private UserFinder $users,
    ) {
    }

    public function show(Request $request): Response
    {
        $claims = $request->attribute('auth');
        $role = $claims instanceof AccessTokenClaims ? $claims->role?->value : null;

        $endpoints = array_values(array_map(
            static fn (array $route): array => [
                'path' => $route['path'],
                'methods' => $route['methods'],
                'description' => $route['description'],
                'accepts' => $route['accepts'],
            ],
            array_filter(
                $this->router->catalog(),
                static fn (array $route): bool => $route['roles'] === [] || ($role !== null && in_array($role, $route['roles'], true)),
            ),
        ));

        return Response::success(['endpoints' => $endpoints, 'me' => $this->me($claims)]);
    }

    /** @return array<string, mixed>|null */
    private function me(mixed $claims): ?array
    {
        if (!$claims instanceof AccessTokenClaims) {
            return null;
        }

        try {
            return UserProfile::fromUser($this->users->findOrFail($claims->subject))->toArray();
        } catch (DomainException) {
            return null;
        }
    }
}
