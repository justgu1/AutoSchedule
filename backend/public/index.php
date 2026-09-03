<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Router;

$app = new Application();

$router = new Router();

$router->get('/api', static function (Request $request) use ($app): JsonResponse {
    return new JsonResponse([
        'message' => 'AutoSchedule API',
        'timezone' => $app->config('timezone'),
        'debug' => $app->config('debug'),
    ]);
});

$response = $router->dispatch(Request::fromGlobals());
$response->send();
