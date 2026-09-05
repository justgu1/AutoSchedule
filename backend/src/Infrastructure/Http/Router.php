<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Router
{
    /**
     * @var array<string, list<array{pattern: string, paramNames: list<string>, handler: callable, roles: list<string>, serviceContext: bool}>>
     */
    private array $routes = [];

    /** @param list<string> $roles vazio = pública; qualquer outro valor = role exigida pelo RoleMiddleware */
    public function get(string $path, callable $handler, array $roles = [], bool $serviceContext = false): void
    {
        $this->add(HttpMethod::Get, $path, $handler, $roles, $serviceContext);
    }

    /** @param list<string> $roles */
    public function post(string $path, callable $handler, array $roles = [], bool $serviceContext = false): void
    {
        $this->add(HttpMethod::Post, $path, $handler, $roles, $serviceContext);
    }

    /** @param list<string> $roles */
    public function put(string $path, callable $handler, array $roles = [], bool $serviceContext = false): void
    {
        $this->add(HttpMethod::Put, $path, $handler, $roles, $serviceContext);
    }

    /** @param list<string> $roles */
    public function patch(string $path, callable $handler, array $roles = [], bool $serviceContext = false): void
    {
        $this->add(HttpMethod::Patch, $path, $handler, $roles, $serviceContext);
    }

    /** @param list<string> $roles */
    public function delete(string $path, callable $handler, array $roles = [], bool $serviceContext = false): void
    {
        $this->add(HttpMethod::Delete, $path, $handler, $roles, $serviceContext);
    }

    /** @param list<string> $roles */
    public function add(HttpMethod|string $method, string $path, callable $handler, array $roles = [], bool $serviceContext = false): void
    {
        $method = $this->resolveMethod($method);

        [$pattern, $paramNames] = $this->compile(Request::normalizePath($path));

        $this->routes[$method->value][] = [
            'pattern' => $pattern,
            'paramNames' => $paramNames,
            'handler' => $handler,
            'roles' => $roles,
            'serviceContext' => $serviceContext,
        ];
    }

    /**
     * Roles exigidos pela rota que bate com $method+$path, sem despachar de
     * verdade -- usado pelo RoleMiddleware pra checar antes do handler rodar.
     * Rota inexistente devolve [] (o 404 de verdade é responsabilidade do
     * dispatch(), não daqui).
     *
     * @return list<string>
     */
    public function requiredRoles(string $method, string $path): array
    {
        return $this->matchRoute($method, $path)['roles'] ?? [];
    }

    /**
     * Se a rota pública que bate com $method+$path precisa de contexto de
     * serviço no RLS (ex: login busca usuário por email antes de existir
     * qualquer autenticação) -- usado pelo AuthContextMiddleware.
     */
    public function isServiceContext(string $method, string $path): bool
    {
        return $this->matchRoute($method, $path)['serviceContext'] ?? false;
    }

    /**
     * @return array{pattern: string, paramNames: list<string>, handler: callable, roles: list<string>, serviceContext: bool}|null
     */
    private function matchRoute(string $method, string $path): ?array
    {
        foreach ($this->routes[strtoupper($method)] ?? [] as $route) {
            if ($this->match($route, $path) !== null) {
                return $route;
            }
        }

        return null;
    }

    private function resolveMethod(HttpMethod|string $method): HttpMethod
    {
        if ($method instanceof HttpMethod) {
            return $method;
        }

        return HttpMethod::tryFrom(strtoupper($method))
            ?? throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route, $path);

            if ($params === null) {
                continue;
            }

            $response = ($route['handler'])($request->withParams($params));

            if (!$response instanceof Response) {
                throw new \LogicException('Route handler must return a Response instance.');
            }

            return $response;
        }

        throw $this->pathMatchesAnyMethod($path)
            ? HttpException::methodNotAllowed()
            : HttpException::notFound();
    }

    private function pathMatchesAnyMethod(string $path): bool
    {
        foreach ($this->routes as $routes) {
            foreach ($routes as $route) {
                if ($this->match($route, $path) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array{pattern: string, paramNames: list<string>, handler: callable} $route
     * @return array<string, string>|null
     */
    private function match(array $route, string $path): ?array
    {
        if (preg_match($route['pattern'], $path, $matches) !== 1) {
            return null;
        }

        $params = [];

        foreach ($route['paramNames'] as $name) {
            $params[$name] = $matches[$name];
        }

        return $params;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function compile(string $path): array
    {
        $paramNames = [];
        $pattern = '';

        // Quebra em literais e placeholders `{param}`, mantendo os placeholders
        // (PREG_SPLIT_DELIM_CAPTURE) pra tratar cada pedaço na hora certa: literal
        // vira preg_quote, placeholder vira grupo nomeado. Evita escapar as chaves
        // do placeholder junto com o resto do path.
        $parts = preg_split(
            '#(\{[a-zA-Z_][a-zA-Z0-9_]*\})#',
            $path,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        foreach ($parts as $part) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$#', $part, $matches) === 1) {
                $paramNames[] = $matches[1];
                $pattern .= '(?P<' . $matches[1] . '>[^/]+)';

                continue;
            }

            $pattern .= preg_quote($part, '#');
        }

        return ['#^' . $pattern . '$#', $paramNames];
    }
}
