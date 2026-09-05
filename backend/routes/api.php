<?php

declare(strict_types=1);

use App\Application;
use App\Domain\Users\DTO\UserProfile;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\UserRole;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Controllers\UserController;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use App\Infrastructure\RateLimit\RateLimitPolicy;

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

    $authRateLimit = $app->config('rate_limit')['auth'];

    $oauthController = $container->get(OAuthController::class);
    $router->post(
        '/api/oauth/token',
        [$oauthController, 'token'],
        serviceContext: true,
        description: 'Logs in with email+password, or renews tokens when refresh_token is sent.',
        accepts: ['client_id', 'email', 'password', 'refresh_token'],
        rateLimit: new RateLimitPolicy('auth', $authRateLimit['max_attempts'], $authRateLimit['window_seconds']),
    );

    $anyAuthenticatedRole = array_map(static fn (UserRole $role): string => $role->value, UserRole::cases());
    $router->post(
        '/api/logout',
        [$oauthController, 'logout'],
        roles: $anyAuthenticatedRole,
        description: 'Revokes the current refresh token family and clears auth cookies.',
    );
    $userController = $container->get(UserController::class);
    $router->post(
        '/api/register',
        [$userController, 'register'],
        serviceContext: true,
        description: 'Creates a seller or customer account.',
        accepts: ['name', 'email', 'phone', 'password', 'role'],
        rateLimit: new RateLimitPolicy('auth', $authRateLimit['max_attempts'], $authRateLimit['window_seconds']),
    );
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
    $router->post(
        '/api/password-reset',
        [$userController, 'requestPasswordReset'],
        serviceContext: true,
        description: 'Sends a password reset link by email, if the address is registered.',
        accepts: ['email'],
        rateLimit: new RateLimitPolicy('auth', $authRateLimit['max_attempts'], $authRateLimit['window_seconds']),
    );
    // Pública (sem `roles`) -- aceita ou `current_password` (autenticado,
    // troca a própria senha) ou `reset_token` (sem Bearer, veio do e-mail de
    // reset). `serviceContext` é o que permite o segundo caminho mexer em
    // `users` sem contexto de usuário nenhum.
    $router->put(
        '/api/me/password',
        [$userController, 'updatePassword'],
        serviceContext: true,
        description: 'Changes your password (current_password when authenticated, or reset_token from the reset email).',
        accepts: ['current_password', 'reset_token', 'password'],
        rateLimit: new RateLimitPolicy('auth', $authRateLimit['max_attempts'], $authRateLimit['window_seconds']),
    );
    $router->delete(
        '/api/me',
        [$userController, 'destroy'],
        roles: $anyAuthenticatedRole,
        description: 'Deletes your account (anonymizes PII and soft-deletes it, per LGPD).',
    );

    $router->get(
        '/api/users',
        [$userController, 'index'],
        roles: ['admin'],
        description: 'Lists users, paginated (query: page, per_page).',
    );
    $router->get(
        '/api/users/{id}',
        [$userController, 'show'],
        roles: ['admin'],
        description: 'Returns a single user.',
    );
    $router->post(
        '/api/users',
        [$userController, 'store'],
        roles: ['admin'],
        description: 'Creates a user.',
        accepts: ['name', 'email', 'phone', 'password', 'role'],
    );
    $router->patch(
        '/api/users/{id}',
        [$userController, 'update'],
        roles: ['admin'],
        description: 'Updates another user\'s name, phone and/or role. Fails if it would leave no admin.',
        accepts: ['name', 'phone', 'role'],
    );
    $router->delete(
        '/api/users/{id}',
        [$userController, 'destroy'],
        roles: ['admin'],
        description: 'Deletes a user (anonymizes PII and soft-deletes it, per LGPD). Fails if it is the last admin.',
    );
};
