<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Ports\DatabaseConnection;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\Middleware\AuthContextMiddleware;
use App\Infrastructure\Http\Middleware\LoggingMiddleware;
use App\Infrastructure\Http\Middleware\RateLimitMiddleware;
use App\Infrastructure\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Logging\Logger;
use App\Infrastructure\RateLimit\RateLimiter;
use App\Infrastructure\RateLimit\RateLimitPolicy;

$app = new Application();
$logger = new Logger();
$container = ContainerFactory::build($app);

$router = new Router();
(require dirname(__DIR__) . '/routes/api.php')($router, $container, $app);

$generalRateLimit = $app->config('rate_limit')['general'];

$pipeline = new Pipeline([
    new LoggingMiddleware(log: static fn (string $line): mixed => $logger->info($line)),
    // Antes de qualquer outro middleware -- barra abuso com um round-trip ao
    // Redis só, sem nem abrir transação no Postgres (AuthContextMiddleware).
    // Construído na mão (não via $container->get()) porque precisa do $router,
    // que não é gerenciado pelo Container -- só existe aqui no index.php.
    new RateLimitMiddleware(
        $container->get(RateLimiter::class),
        $router,
        new RateLimitPolicy('general', $generalRateLimit['max_attempts'], $generalRateLimit['window_seconds']),
        $container->get(TokenIssuer::class),
        $logger,
    ),
    new AuthContextMiddleware($container->get(TokenIssuer::class), $container->get(DatabaseConnection::class), $router),
    new RoleMiddleware($router),
]);

$exceptionHandler = new ExceptionHandler(debug: (bool) $app->config('debug'), logger: $logger);

// O dispatch do router fica embrulhado aqui dentro, no destino do pipeline,
// não em volta do pipeline inteiro -- assim todo middleware ainda roda sua
// metade "depois" (ex: LoggingMiddleware logando o status real) mesmo quando
// o handler lançou exceção.
$destination = static function (Request $request) use ($router, $exceptionHandler): Response {
    try {
        return $router->dispatch($request);
    } catch (\Throwable $exception) {
        return $exceptionHandler->handle($exception);
    }
};

try {
    $response = $pipeline->process(Request::fromGlobals(), $destination);
} catch (\Throwable $exception) {
    // Rede de segurança só: um bug dentro de um middleware em si, não dentro
    // de um route handler.
    $response = $exceptionHandler->handle($exception);
}

$response->send();
