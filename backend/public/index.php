<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\Middleware\LoggingMiddleware;
use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Logging\Logger;

$app = new Application();
$logger = new Logger();

$router = new Router();

$router->get('/api', static function (Request $request) use ($app): Response {
    return Response::success([
        'message' => 'AutoSchedule API',
        'timezone' => $app->config('timezone'),
        'debug' => $app->config('debug'),
    ]);
});

$pipeline = new Pipeline([
    new LoggingMiddleware(log: static fn (string $line): mixed => $logger->info($line)),
]);

$exceptionHandler = new ExceptionHandler(debug: (bool) $app->config('debug'), logger: $logger);

try {
    $response = $pipeline->process(
        Request::fromGlobals(),
        static fn (Request $request): Response => $router->dispatch($request),
    );
} catch (\Throwable $exception) {
    $response = $exceptionHandler->handle($exception);
}

$response->send();
