<?php

declare(strict_types=1);

use App\Domain\Shared\TrashState;
use App\Domain\User\UserRole;
use App\Infrastructure\Http\Controllers\ApiCatalogController;
use App\Infrastructure\Http\Controllers\ApiClientController;
use App\Infrastructure\Http\Controllers\AppointmentController;
use App\Infrastructure\Http\Controllers\AvailabilityExceptionController;
use App\Infrastructure\Http\Controllers\DealershipAvailabilityController;
use App\Infrastructure\Http\Controllers\DealershipController;
use App\Infrastructure\Http\Controllers\JobController;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Controllers\UserController;
use App\Infrastructure\Http\Controllers\VehicleAvailabilityController;
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

    $router->get('/api/vehicles', [VehicleController::class, 'index'])
        ->publicRead()
        ->describes('Without scope, the public catalog (everyone sees the same active stock, logged in or not). With scope=mine (requires admin/seller), the caller\'s own inventory. Query: q, sort, brand, model, year_min, year_max, price_min, price_max, dealership_id, transmission, body_type, fuel_type, mileage_km_max, page, per_page. Brand and model go through the text index, so they also match a vehicle that only mentions them in the description. A small per_page with q also works as typeahead suggestions -- no separate endpoint for that.');

    // Antes de `{id}`: o router devolve a primeira rota que casa, e o parâmetro engoliria "filters"/"amenities-catalog".
    $router->get('/api/vehicles/filters', [VehicleController::class, 'filters'])
        ->publicRead()
        ->describes('Brands, models, years, transmissions, body types and fuel types in stock, to fill the filter inputs. Public catalog by default, caller\'s own stock with scope=mine.');

    $router->get('/api/vehicles/amenities-catalog', [VehicleController::class, 'amenitiesCatalog'])
        ->publicRead()
        ->describes('The full, global catalog of vehicle amenities/equipment items (seeded, read-only).');

    $router->get('/api/vehicles/{id}', [VehicleController::class, 'show'])
        ->publicRead()
        ->describes('Returns a vehicle -- full profile for its owner/admin, public profile (with the dealership it belongs to) for anyone else. Only active vehicles of an active dealership are visible to non-owners.');

    $router->get('/api/vehicles/{id}/availability/dates', [VehicleAvailabilityController::class, 'dates'])
        ->publicRead()
        ->describes('Dates within a month (query: month, default current) that have at least one free slot to book a test drive.');

    $router->get('/api/vehicles/{id}/availability/slots', [VehicleAvailabilityController::class, 'slots'])
        ->publicRead()
        ->describes('Free time slots for one date (query: date, required).');

    $router->post('/api/appointments', [AppointmentController::class, 'store'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Books a test drive, no account required -- finds or creates a customer account by email.')
        ->accepts('vehicle_id', 'scheduled_at', 'customer_name', 'customer_email', 'customer_phone');

    $router->get('/api/appointments/{id}', [AppointmentController::class, 'show'])
        ->serviceContext()
        ->describes('Returns an appointment -- the caller\'s own session (owner/admin) or the token from the confirmation email (query: token).');

    $router->post('/api/appointments/{id}/confirm', [AppointmentController::class, 'confirm'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Confirms a pending appointment -- the caller\'s own session (owner/admin) or the token from the confirmation email.')
        ->accepts('token');

    $router->post('/api/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])
        ->serviceContext()
        ->rateLimit('auth')
        ->describes('Cancels a pending appointment -- the caller\'s own session (owner/admin) or the token from the confirmation email.')
        ->accepts('token');

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

        $router->get('/api/me/api-clients', [ApiClientController::class, 'index'])
            ->describes('Lists your own client_credentials API clients (metadata only, never the secret).');

        $router->post('/api/me/api-clients', [ApiClientController::class, 'store'])
            ->describes('Creates a new client_credentials client for you (m2m -- it authenticates as you, same role/RLS as your session). Returns client_id and client_secret in plain text ONCE.')
            ->accepts('name');

        $router->post('/api/me/api-clients/{id}/rotate-secret', [ApiClientController::class, 'rotateSecret'])
            ->describes('Regenerates the secret of one of your clients, keeping the same client_id. Returns the new secret in plain text ONCE.');

        $router->delete('/api/me/api-clients/{id}', [ApiClientController::class, 'destroy'])
            ->describes('Revokes one of your clients. Tokens already issued from it remain valid until they expire.');

        $router->get('/api/jobs/{id}', [JobController::class, 'show'])
            ->describes('Returns the current status of an async job (queued/processing/done/failed).');

        $router->get('/api/jobs/{id}/events', [JobController::class, 'events'])
            ->describes('Streams the status of an async job via Server-Sent Events until it finishes.');
    });

    $router->group([UserRole::Admin], static function (Router $router): void {
        $router->get('/api/users', [UserController::class, 'index'])
            ->describes('Lists users, paginated (query: page, per_page, role).');

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

        $router->post('/api/vehicles', [VehicleController::class, 'store'])
            ->describes('Creates a vehicle in one of the caller dealerships.')
            ->accepts('dealership_id', 'brand', 'model', 'version', 'manufacture_year', 'model_year', 'price', 'description', 'mileage_km', 'transmission', 'body_type', 'fuel_type', 'color', 'plate_end_digit', 'accepts_trade', 'ipva_paid', 'licensed', 'amenity_ids');

        $router->patch('/api/vehicles/{id}', [VehicleController::class, 'update'])
            ->describes('Updates vehicle fields. Sending dealership_id moves it to another dealership the caller can reach. amenity_ids, when sent, replaces the whole set.')
            ->accepts('dealership_id', 'brand', 'model', 'version', 'manufacture_year', 'model_year', 'price', 'description', 'mileage_km', 'transmission', 'body_type', 'fuel_type', 'color', 'plate_end_digit', 'accepts_trade', 'ipva_paid', 'licensed', 'amenity_ids');

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

        $router->get('/api/dealerships/{id}/availability-rules', [DealershipAvailabilityController::class, 'index'])
            ->describes('Lists the recurring weekly availability windows of a dealership.');

        $router->post('/api/dealerships/{id}/availability-rules', [DealershipAvailabilityController::class, 'store'])
            ->describes('Adds a recurring weekly availability window to a dealership.')
            ->accepts('weekday', 'start_time', 'end_time');

        $router->patch('/api/availability-rules/{id}', [DealershipAvailabilityController::class, 'update'])
            ->describes('Updates a dealership availability rule.')
            ->accepts('weekday', 'start_time', 'end_time');

        $router->delete('/api/availability-rules/{id}', [DealershipAvailabilityController::class, 'destroy'])
            ->describes('Removes a dealership availability rule.');

        $router->get('/api/vehicles/{id}/availability-rules', [VehicleAvailabilityController::class, 'index'])
            ->describes('Lists the recurring weekly availability windows of a vehicle.');

        $router->post('/api/vehicles/{id}/availability-rules', [VehicleAvailabilityController::class, 'store'])
            ->describes('Adds a recurring weekly availability window to a vehicle.')
            ->accepts('weekday', 'start_time', 'end_time');

        $router->patch('/api/vehicle-availability-rules/{id}', [VehicleAvailabilityController::class, 'update'])
            ->describes('Updates a vehicle availability rule.')
            ->accepts('weekday', 'start_time', 'end_time');

        $router->delete('/api/vehicle-availability-rules/{id}', [VehicleAvailabilityController::class, 'destroy'])
            ->describes('Removes a vehicle availability rule.');

        $router->get('/api/availability-exceptions', [AvailabilityExceptionController::class, 'index'])
            ->describes('Lists exceptions for a dealership or a vehicle (query: dealership_id or vehicle_id, exactly one).');

        $router->post('/api/availability-exceptions', [AvailabilityExceptionController::class, 'store'])
            ->describes('Blocks, opens, or narrows availability on one date for a dealership or a vehicle.')
            ->accepts('dealership_id', 'vehicle_id', 'date', 'start_time', 'end_time', 'is_available', 'reason');

        $router->patch('/api/availability-exceptions/{id}', [AvailabilityExceptionController::class, 'update'])
            ->describes('Updates an availability exception. The scope (dealership/vehicle) cannot change.')
            ->accepts('date', 'start_time', 'end_time', 'is_available', 'reason');

        $router->delete('/api/availability-exceptions/{id}', [AvailabilityExceptionController::class, 'destroy'])
            ->describes('Removes an availability exception.');

        $router->get('/api/appointments', [AppointmentController::class, 'index'])
            ->describes('Lists appointments -- admin sees all, seller sees only the ones on their own vehicles (query: status, vehicle_id, page, per_page).');

        $router->post('/api/appointments/{id}/pickup', [AppointmentController::class, 'pickup'])
            ->describes('Marks the vehicle as picked up for a confirmed appointment\'s test drive.');

        $router->post('/api/appointments/{id}/release', [AppointmentController::class, 'release'])
            ->describes('Marks the vehicle as returned -- completes the appointment.');
    });
};
