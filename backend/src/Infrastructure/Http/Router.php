<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\User\UserRole;
use App\Infrastructure\Container\Container;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<UserRole> roles herdadas pelo `group()` que estiver aberto */
    private array $groupRoles = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    public function get(string $path, array|\Closure $handler): Route
    {
        return $this->add(HttpMethod::Get, $path, $handler);
    }

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    public function post(string $path, array|\Closure $handler): Route
    {
        return $this->add(HttpMethod::Post, $path, $handler);
    }

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    public function put(string $path, array|\Closure $handler): Route
    {
        return $this->add(HttpMethod::Put, $path, $handler);
    }

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    public function patch(string $path, array|\Closure $handler): Route
    {
        return $this->add(HttpMethod::Patch, $path, $handler);
    }

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    public function delete(string $path, array|\Closure $handler): Route
    {
        return $this->add(HttpMethod::Delete, $path, $handler);
    }

    /**
     * @param list<UserRole> $roles
     * @param \Closure(self): void $routes
     */
    public function group(array $roles, \Closure $routes): void
    {
        $previous = $this->groupRoles;
        $this->groupRoles = $roles;

        try {
            $routes($this);
        } finally {
            $this->groupRoles = $previous;
        }
    }

    public function match(string $method, string $path): ?Route
    {
        $normalized = Request::normalizePath($path);

        foreach ($this->routes as $route) {
            if ($route->method->value === strtoupper($method) && $route->match($normalized) !== null) {
                return $route;
            }
        }

        return null;
    }

    /** Distingue 405 de 404 sem outra varredura completa nas rotas do método pedido. */
    public function pathExistsForAnotherMethod(string $path): bool
    {
        $normalized = Request::normalizePath($path);
        return array_any($this->routes, fn ($route) => $route->match($normalized) !== null);
    }

    /** Usa a rota já casada pelo pipeline, então não varre a tabela de novo. */
    public function dispatch(Request $request): Response
    {
        $route = $request->route();

        if (!$route instanceof Route) {
            throw $this->pathExistsForAnotherMethod($request->path())
                ? HttpException::methodNotAllowed()
                : HttpException::notFound();
        }

        $params = $route->match($request->path()) ?? [];
        $response = $this->invoke($route, $request->withParams($params));

        if (!$response instanceof Response) {
            throw new \LogicException('Route handler must return a Response instance.');
        }

        return $response;
    }

    /**
     * `roles` acompanha só pra quem monta a resposta filtrar por acesso; nunca vai pro cliente.
     *
     * @return list<array{path: string, methods: list<string>, description: string, accepts: list<string>, roles: list<string>}>
     */
    public function catalog(): array
    {
        return array_map(
            static fn (Route $route): array => [
                'path' => $route->path,
                'methods' => [$route->method->value],
                'description' => $route->description(),
                'accepts' => $route->acceptedFields(),
                'roles' => array_map(static fn (UserRole $role): string => $role->value, $route->requiredRoles()),
            ],
            $this->routes,
        );
    }

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    private function add(HttpMethod $method, string $path, array|\Closure $handler): Route
    {
        $route = new Route($method, Request::normalizePath($path), $handler);

        if ($this->groupRoles !== []) {
            $route->roles(...$this->groupRoles);
        }

        $this->routes[] = $route;

        return $route;
    }

    private function invoke(Route $route, Request $request): mixed
    {
        $handler = $route->handler();

        if ($handler instanceof \Closure) {
            return $handler($request);
        }

        [$class, $method] = $handler;

        return $this->container->get($class)->{$method}($request);
    }
}
