<?php

declare(strict_types=1);

use App\Application;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

/**
 * Registra toda rota da API. Só sabe QUAIS rotas existem e qual handler cada
 * uma usa -- quem monta cada dependência é o Bootstrap\ContainerFactory.
 */
return static function (Router $router, Container $container, Application $app): void {
    $router->get('/api', static function (Request $request) use ($app): Response {
        return Response::success([
            'message' => 'AutoSchedule API',
            'timezone' => $app->config('timezone'),
            'debug' => $app->config('debug'),
        ]);
    });

    $oauthController = $container->get(OAuthController::class);
    $router->post('/api/oauth/token', [$oauthController, 'token'], serviceContext: true);
};
