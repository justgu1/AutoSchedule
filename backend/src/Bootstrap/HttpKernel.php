<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

final readonly class HttpKernel
{
    private function __construct(
        private Router $router,
        private Pipeline $pipeline,
        private ExceptionHandler $exceptions,
    ) {
    }

    public static function boot(): self
    {
        $config = new Config();
        $container = ContainerFactory::build($config);

        $router = $container->get(Router::class);

        /** @var \Closure(Router): void $registerRoutes */
        $registerRoutes = require dirname(__DIR__, 2) . '/routes/api.php';
        $registerRoutes($router);

        return new self(
            $router,
            PipelineFactory::build($container, $config),
            $container->get(ExceptionHandler::class),
        );
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->pipeline->process($request, $this->dispatch(...));
        } catch (\Throwable $exception) {
            // Rede de segurança pra bug dentro de middleware; erro de handler já é tratado no destino.
            return $this->exceptions->handle($exception);
        }
    }

    /**
     * O dispatch fica DENTRO do pipeline pra todo middleware ainda rodar a metade "depois"
     * quando o handler lança -- é o que faz o log sair com o status real.
     */
    private function dispatch(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $exception) {
            return $this->exceptions->handle($exception);
        }
    }
}
