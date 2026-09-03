<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

$app = new Application();

$router = new Router();

$router->get('/api', static function (Request $request) use ($app): Response {
    return Response::success([
        'message' => 'AutoSchedule API',
        'timezone' => $app->config('timezone'),
        'debug' => $app->config('debug'),
    ]);
});

$exceptionHandler = new ExceptionHandler(debug: (bool) $app->config('debug'));

try {
    $response = $router->dispatch(Request::fromGlobals());
} catch (\Throwable $exception) {
    $response = $exceptionHandler->handle($exception);
}

$response->send();
