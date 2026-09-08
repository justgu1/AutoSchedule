<?php

declare(strict_types=1);

use App\Domain\Shared\TrashState;
use App\Domain\User\UserRole;
use App\Infrastructure\Http\Controllers\ApiCatalogController;
use App\Infrastructure\Http\Controllers\DealershipController;
use App\Infrastructure\Http\Controllers\JobController;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Controllers\UserController;
use App\Infrastructure\Http\Controllers\VehicleController;
use App\Infrastructure\Http\Controllers\ZipCodeController;
use App\Infrastructure\Http\Router;

return static function (Router $router): void {
    $anyRole = [UserRole::Admin, UserRole::Seller, UserRole::Customer];
    $adminOrSeller = [UserRole::Admin, UserRole::Seller];

    $router->get('/api', [ApiCatalogController::class, 'show'])
        ->describes('Lists the endpoints your role can access.');

    $router->post('/api/oauth/token', [OAuthController::class, 'token'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Logs in with email+password, renews tokens when refresh_token is sent, logs in with Google when id_token is sent, or issues a machine-to-machine token with client_id+client_secret.')
        ->accepts('client_id', 'email', 'password', 'refresh_token', 'id_token', 'client_secret');

    $router->post('/api/register', [UserController::class, 'register'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Creates a seller or customer account.')
        ->accepts('name', 'email', 'phone', 'password', 'role');

    $router->post('/api/password-reset', [UserController::class, 'requestPasswordReset'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Sends a password reset link by email, if the address is registered.')
        ->accepts('email');

    // Pública porque aceita os dois caminhos: `current_password` autenticado, ou `reset_token` sem Bearer nenhum.
    $router->put('/api/me/password', [UserController::class, 'updatePassword'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Changes your password (current_password when authenticated, or reset_token from the reset email).')
        ->accepts('current_password', 'reset_token', 'password');

    $router->get('/api/dealerships/{id}', [DealershipController::class, 'show'])
        ->publicRead()
        ->describes('Returns a dealership -- full profile for its owner/admin, public-safe profile (name only, no other seller data) for anyone else, including no account at all. Only active dealerships are visible to non-owners.');

    $router->get('/api/zip-codes/{zip_code}', [ZipCodeController::class, 'show'])
        ->describes('Resolves a Brazilian CEP into street/neighborhood/city/state (ViaCEP, cached after the first lookup).');

    $router->group($anyRole, static function (Router $router): void {
        $router->post('/api/logout', [OAuthController::class, 'logout'])
            ->describes('Revokes the current refresh token family and clears auth cookies.');

        $router->get('/api/me', [UserController::class, 'show'])
            ->describes('Returns your profile.');

        $router->patch('/api/me', [UserController::class, 'update'])
            ->describes('Updates your name and/or phone; customer accounts may also self-upgrade to seller.')
            ->accepts('name', 'phone', 'role');

        $router->delete('/api/me', [UserController::class, 'destroy'])
            ->describes(sprintf('Moves your account to trash (recoverable for %d days by logging in again, or restored/purged by an admin).', TrashState::GRACE_DAYS));

        $router->post('/api/me/purge', [UserController::class, 'purge'])
            ->describes(sprintf('Permanently anonymizes your trashed account now, without waiting %d days.', TrashState::GRACE_DAYS));

        $router->get('/api/jobs/{id}', [JobController::class, 'show'])
            ->describes('Returns the current status of an async job (queued/processing/done/failed).');

        $router->get('/api/jobs/{id}/events', [JobController::class, 'events'])
            ->describes('Streams the status of an async job via Server-Sent Events until it finishes.');
    });

    $router->group([UserRole::Admin], static function (Router $router): void {
        $router->get('/api/users', [UserController::class, 'index'])
            ->describes('Lists users, paginated (query: page, per_page).');

        $router->get('/api/users/{id}', [UserController::class, 'show'])
            ->describes('Returns a single user.');

        $router->post('/api/users', [UserController::class, 'store'])
            ->describes('Creates a user.')
            ->accepts('name', 'email', 'phone', 'password', 'role');

        $router->patch('/api/users/{id}', [UserController::class, 'update'])
            ->describes('Updates another user\'s name, phone and/or role. Fails if it would leave no admin.')
            ->accepts('name', 'phone', 'role');

        $router->delete('/api/users/{id}', [UserController::class, 'destroy'])
            ->describes('Moves a user to trash. Fails if it is the last admin.');

        $router->post('/api/users/{id}/restore', [UserController::class, 'restore'])
            ->describes('Restores a trashed user before the recovery window expires.');

        $router->post('/api/users/{id}/purge', [UserController::class, 'purge'])
            ->describes(sprintf('Permanently anonymizes a trashed user now, without waiting %d days.', TrashState::GRACE_DAYS));
    });

    $router->group($adminOrSeller, static function (Router $router): void {
        $router->get('/api/dealerships', [DealershipController::class, 'index'])
            ->describes('Lists dealerships -- admin sees all (paginated), seller sees only their own.');

        $router->post('/api/dealerships', [DealershipController::class, 'store'])
            ->describes('Creates a dealership. Seller becomes the owner automatically; admin must send owner_user_id.')
            ->accepts('name', 'zip_code', 'address', 'number', 'complement', 'neighborhood', 'city', 'state', 'phone', 'email', 'owner_user_id');

        $router->patch('/api/dealerships/{id}', [DealershipController::class, 'update'])
            ->describes('Updates dealership profile fields. Admin may also send owner_user_id to reassign it to another seller.')
            ->accepts('name', 'zip_code', 'address', 'number', 'complement', 'neighborhood', 'city', 'state', 'phone', 'email', 'owner_user_id');

        $router->delete('/api/dealerships/{id}', [DealershipController::class, 'destroy'])
            ->describes('Moves a dealership to trash.');

        $router->post('/api/dealerships/{id}/restore', [DealershipController::class, 'restore'])
            ->describes('Restores a trashed dealership before the recovery window expires.');

        $router->post('/api/dealerships/{id}/purge', [DealershipController::class, 'purge'])
            ->describes(sprintf('Permanently anonymizes a trashed dealership now, without waiting %d days.', TrashState::GRACE_DAYS));

        $router->post('/api/dealerships/{id}/photo', [DealershipController::class, 'setPhoto'])
            ->describes('Sets the dealership photo (multipart, field name "image", max 20MB) -- replaces the previous one, if any.');

        $router->delete('/api/dealerships/{id}/photo', [DealershipController::class, 'removePhoto'])
            ->describes('Removes the dealership photo.');

        $router->get('/api/vehicles', [VehicleController::class, 'index'])
            ->describes('Lists and searches vehicles -- admin sees all, seller sees only the ones in their own dealerships (query: q, brand, model, year_min, year_max, price_min, price_max, dealership_id, page, per_page). Brand and model go through the text index, so they also match a vehicle that only mentions them in the description.');

        // Antes de `{id}`: o router devolve a primeira rota que casa, e o parâmetro engoliria "filters".
        $router->get('/api/vehicles/filters', [VehicleController::class, 'filters'])
            ->describes('Brands, models and years that exist in the caller stock, to fill the filter inputs.');

        $router->get('/api/vehicles/{id}', [VehicleController::class, 'show'])
            ->describes('Returns a vehicle owned by the caller (or any vehicle, for an admin).');

        $router->post('/api/vehicles', [VehicleController::class, 'store'])
            ->describes('Creates a vehicle in one of the caller dealerships.')
            ->accepts('dealership_id', 'brand', 'model', 'version', 'year', 'price', 'description');

        $router->patch('/api/vehicles/{id}', [VehicleController::class, 'update'])
            ->describes('Updates vehicle fields. Sending dealership_id moves it to another dealership the caller can reach.')
            ->accepts('dealership_id', 'brand', 'model', 'version', 'year', 'price', 'description');

        $router->delete('/api/vehicles/{id}', [VehicleController::class, 'destroy'])
            ->describes('Moves a vehicle to trash.');

        $router->post('/api/vehicles/{id}/restore', [VehicleController::class, 'restore'])
            ->describes('Restores a trashed vehicle before the recovery window expires.');

        $router->post('/api/vehicles/{id}/purge', [VehicleController::class, 'purge'])
            ->describes(sprintf('Permanently deletes a trashed vehicle now, without waiting %d days.', TrashState::GRACE_DAYS));

        $router->post('/api/vehicles/{id}/photos', [VehicleController::class, 'addPhotos'])
            ->describes('Adds images to the vehicle gallery (multipart, field name "images[]", up to 10 files of 20MB each) -- one job id tracks the whole batch.');

        $router->patch('/api/vehicles/{id}/photos', [VehicleController::class, 'reorderPhotos'])
            ->describes('Reorders the gallery. Send every image id of the vehicle, in the wanted order; the first one becomes the cover.')
            ->accepts('order');

        $router->delete('/api/vehicles/{id}/photos/{image_id}', [VehicleController::class, 'removePhoto'])
            ->describes('Removes one image from the vehicle gallery.');
    });
};
