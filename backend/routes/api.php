<?php

declare(strict_types=1);

use App\Application;
use App\Domain\Users\DTO\UserProfile;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\UserRole;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Http\Controllers\DealershipController;
use App\Infrastructure\Http\Controllers\JobController;
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
        description: 'Logs in with email+password, renews tokens when refresh_token is sent, logs in with Google when id_token is sent, or issues a machine-to-machine token with client_id+client_secret.',
        accepts: ['client_id', 'email', 'password', 'refresh_token', 'id_token', 'client_secret'],
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
        description: 'Updates your name and/or phone; customer accounts may also self-upgrade to seller.',
        accepts: ['name', 'phone', 'role'],
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
        description: 'Moves your account to trash (recoverable for 30 days by logging in again, or restored/purged by an admin).',
    );
    $router->post(
        '/api/me/purge',
        [$userController, 'purge'],
        roles: $anyAuthenticatedRole,
        description: 'Permanently anonymizes your trashed account now, without waiting 30 days.',
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
        description: 'Moves a user to trash. Fails if it is the last admin.',
    );
    $router->post(
        '/api/users/{id}/restore',
        [$userController, 'restore'],
        roles: ['admin'],
        description: 'Restores a trashed user before the 30-day window expires.',
    );
    $router->post(
        '/api/users/{id}/purge',
        [$userController, 'purge'],
        roles: ['admin'],
        description: 'Permanently anonymizes a trashed user now, without waiting 30 days.',
    );

    $adminOrSeller = ['admin', 'seller'];
    $dealershipController = $container->get(DealershipController::class);
    $router->get(
        '/api/dealerships',
        [$dealershipController, 'index'],
        roles: $adminOrSeller,
        description: 'Lists dealerships -- admin sees all (paginated), seller sees only their own.',
    );
    $router->post(
        '/api/dealerships',
        [$dealershipController, 'store'],
        roles: $adminOrSeller,
        description: 'Creates a dealership. Seller becomes the owner automatically; admin must send owner_user_id.',
        accepts: ['name', 'zip_code', 'address', 'number', 'complement', 'neighborhood', 'city', 'state', 'phone', 'owner_user_id'],
    );
    $router->get(
        '/api/dealerships/{id}',
        [$dealershipController, 'show'],
        roles: $adminOrSeller,
        description: 'Returns a single dealership.',
    );
    $router->patch(
        '/api/dealerships/{id}',
        [$dealershipController, 'update'],
        roles: $adminOrSeller,
        description: 'Updates dealership profile fields. Admin may also send owner_user_id to reassign it to another seller.',
        accepts: ['name', 'zip_code', 'address', 'number', 'complement', 'neighborhood', 'city', 'state', 'phone', 'owner_user_id'],
    );
    $router->delete(
        '/api/dealerships/{id}',
        [$dealershipController, 'destroy'],
        roles: $adminOrSeller,
        description: 'Moves a dealership to trash.',
    );
    $router->post(
        '/api/dealerships/{id}/restore',
        [$dealershipController, 'restore'],
        roles: $adminOrSeller,
        description: 'Restores a trashed dealership before the 30-day window expires.',
    );
    $router->post(
        '/api/dealerships/{id}/purge',
        [$dealershipController, 'purge'],
        roles: $adminOrSeller,
        description: 'Permanently anonymizes a trashed dealership now, without waiting 30 days.',
    );
    $router->post(
        '/api/dealerships/{id}/photo',
        [$dealershipController, 'setPhoto'],
        roles: $adminOrSeller,
        description: 'Sets the dealership photo (multipart, field name "image", max 20MB) -- replaces the previous one, if any.',
    );
    $router->delete(
        '/api/dealerships/{id}/photo',
        [$dealershipController, 'removePhoto'],
        roles: $adminOrSeller,
        description: 'Removes the dealership photo.',
    );

    $jobController = $container->get(JobController::class);
    $router->get(
        '/api/jobs/{id}',
        [$jobController, 'show'],
        roles: $anyAuthenticatedRole,
        description: 'Returns the current status of an async job (queued/processing/done/failed).',
    );
    $router->get(
        '/api/jobs/{id}/events',
        [$jobController, 'events'],
        roles: $anyAuthenticatedRole,
        description: 'Streams the status of an async job via Server-Sent Events until it finishes.',
    );
};
