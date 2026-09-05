<?php

declare(strict_types=1);

use App\Application;
use App\Domain\Users\DTO\UserProfile;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\UserRole;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Controllers\UserController;
use App\Infrastructure\Http\Controllers\UsersController;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

/**
 * Registra toda rota da API. Só sabe QUAIS rotas existem e qual handler cada
 * uma usa -- quem monta cada dependência é o Bootstrap\ContainerFactory.
 */
return static function (Router $router, Container $container, Application $app): void {
    // Catálogo: lista toda rota pública + toda rota cujo role exigido bate com
    // o Bearer enviado (se houver) -- só mostra pro cliente o que ele de fato
    // pode chamar, por isso `roles` não vai na resposta (o cliente não decide
    // nada com isso, só filtramos aqui). $router->catalog() é lido em
    // request-time, não aqui, então já reflete toda rota registrada abaixo
    // mesmo estando declarada antes.
    $router->get('/api', static function (Request $request) use ($container, $router): Response {
        $claims = $request->attribute('auth');
        $role = $claims?->role?->value;

        $endpoints = array_values(array_map(
            static fn (array $route): array => [
                'path' => $route['path'],
                'methods' => $route['methods'],
                'description' => $route['description'],
                'accepts' => $route['accepts'],
            ],
            array_filter(
                $router->catalog(),
                static fn (array $route): bool => $route['roles'] === [] || ($role !== null && in_array($role, $route['roles'], true)),
            ),
        ));

        $me = null;

        if ($claims !== null) {
            $user = $container->get(UserRepository::class)->findById($claims->subject);
            $me = $user !== null ? UserProfile::fromUser($user)->toArray() : null;
        }

        return Response::success(['endpoints' => $endpoints, 'me' => $me]);
    }, description: 'Lists the endpoints your role can access.');

    $oauthController = $container->get(OAuthController::class);
    $router->post(
        '/api/oauth/token',
        [$oauthController, 'token'],
        serviceContext: true,
        description: 'Logs in with email+password, or renews tokens when refresh_token is sent.',
        accepts: ['client_id', 'email', 'password', 'refresh_token'],
    );

    $anyAuthenticatedRole = array_map(static fn (UserRole $role): string => $role->value, UserRole::cases());
    $userController = $container->get(UserController::class);
    $router->get(
        '/api/me',
        [$userController, 'show'],
        roles: $anyAuthenticatedRole,
        description: 'Returns your profile.',
    );
    $router->patch(
        '/api/me',
        [$userController, 'update'],
        roles: $anyAuthenticatedRole,
        description: 'Updates your name and/or phone.',
        accepts: ['name', 'phone'],
    );
    $router->put(
        '/api/me/password',
        [$userController, 'updatePassword'],
        roles: $anyAuthenticatedRole,
        description: 'Changes your password (requires the current one).',
        accepts: ['current_password', 'password'],
    );
    $router->delete(
        '/api/me',
        [$userController, 'destroy'],
        roles: $anyAuthenticatedRole,
        description: 'Deletes your account (anonymizes PII and soft-deletes it, per LGPD).',
    );

    $usersController = $container->get(UsersController::class);
    $router->get(
        '/api/users',
        [$usersController, 'index'],
        roles: ['admin'],
        description: 'Lists every user.',
    );
    $router->get(
        '/api/users/{id}',
        [$usersController, 'show'],
        roles: ['admin'],
        description: 'Returns a single user.',
    );
    $router->post(
        '/api/users',
        [$usersController, 'store'],
        roles: ['admin'],
        description: 'Creates a user.',
        accepts: ['name', 'email', 'phone', 'password', 'role'],
    );
    $router->patch(
        '/api/users/{id}',
        [$usersController, 'update'],
        roles: ['admin'],
        description: 'Updates another user\'s name, phone and/or role.',
        accepts: ['name', 'phone', 'role'],
    );
    $router->delete(
        '/api/users/{id}',
        [$usersController, 'destroy'],
        roles: ['admin'],
        description: 'Deletes a user (anonymizes PII and soft-deletes it, per LGPD). Fails if it is the last admin.',
    );
};
