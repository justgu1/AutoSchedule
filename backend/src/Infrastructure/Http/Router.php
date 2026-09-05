<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\RateLimit\RateLimitPolicy;

final class Router
{
    /**
     * @var array<string, list<array{path: string, pattern: string, paramNames: list<string>, handler: callable, roles: list<string>, serviceContext: bool, description: string, accepts: list<string>, rateLimit: ?RateLimitPolicy}>>
     */
    private array $routes = [];

    /**
     * @param list<string> $roles vazio = pública; qualquer outro valor = role exigida pelo RoleMiddleware
     * @param list<string> $accepts nomes dos campos aceitos no corpo da requisição -- só documentação do catálogo (GET /api), o Validator continua sendo a fonte de verdade da validação
     * @param ?RateLimitPolicy $rateLimit null = usa a policy geral do RateLimitMiddleware; só rotas mais sensíveis (ex: login) precisam de uma própria
     */
    public function get(string $path, callable $handler, array $roles = [], bool $serviceContext = false, string $description = '', array $accepts = [], ?RateLimitPolicy $rateLimit = null): void
    {
        $this->add(HttpMethod::Get, $path, $handler, $roles, $serviceContext, $description, $accepts, $rateLimit);
    }

    public function post(string $path, callable $handler, array $roles = [], bool $serviceContext = false, string $description = '', array $accepts = [], ?RateLimitPolicy $rateLimit = null): void
    {
        $this->add(HttpMethod::Post, $path, $handler, $roles, $serviceContext, $description, $accepts, $rateLimit);
    }

    public function put(string $path, callable $handler, array $roles = [], bool $serviceContext = false, string $description = '', array $accepts = [], ?RateLimitPolicy $rateLimit = null): void
    {
        $this->add(HttpMethod::Put, $path, $handler, $roles, $serviceContext, $description, $accepts, $rateLimit);
    }

    public function patch(string $path, callable $handler, array $roles = [], bool $serviceContext = false, string $description = '', array $accepts = [], ?RateLimitPolicy $rateLimit = null): void
    {
        $this->add(HttpMethod::Patch, $path, $handler, $roles, $serviceContext, $description, $accepts, $rateLimit);
    }

    public function delete(string $path, callable $handler, array $roles = [], bool $serviceContext = false, string $description = '', array $accepts = [], ?RateLimitPolicy $rateLimit = null): void
    {
        $this->add(HttpMethod::Delete, $path, $handler, $roles, $serviceContext, $description, $accepts, $rateLimit);
    }

    /** Todo `get/post/put/patch/delete` empurra pra cá -- é aqui, só uma vez, que o formato da rota registrada é decidido. */
    public function add(HttpMethod|string $method, string $path, callable $handler, array $roles = [], bool $serviceContext = false, string $description = '', array $accepts = [], ?RateLimitPolicy $rateLimit = null): void
    {
        $method = $this->resolveMethod($method);
        $normalizedPath = Request::normalizePath($path);

        [$pattern, $paramNames] = $this->compile($normalizedPath);

        $this->routes[$method->value][] = [
            'path' => $normalizedPath,
            'pattern' => $pattern,
            'paramNames' => $paramNames,
            'handler' => $handler,
            'roles' => $roles,
            'serviceContext' => $serviceContext,
            'description' => $description,
            'accepts' => $accepts,
            'rateLimit' => $rateLimit,
        ];
    }

    /**
     * Catálogo de toda rota registrada -- usado pelo endpoint raiz da API
     * (`GET /api`) pra listar o que existe, sem expor o handler em si. `roles`
     * vai junto só pra quem monta a resposta poder filtrar por acesso -- não
     * é pra vazar pro cliente (ele já recebe só o que o próprio role alcança).
     *
     * @return list<array{path: string, methods: list<string>, description: string, accepts: list<string>, roles: list<string>}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $catalog[] = [
                    'path' => $route['path'],
                    'methods' => [$method],
                    'description' => $route['description'],
                    'accepts' => $route['accepts'],
                    'roles' => $route['roles'],
                ];
            }
        }

        return $catalog;
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
     * Policy própria da rota que bate com $method+$path, ou null (usa a geral
     * do RateLimitMiddleware) quando a rota não declarou uma -- ou não existe.
     */
    public function rateLimitPolicy(string $method, string $path): ?RateLimitPolicy
    {
        return $this->matchRoute($method, $path)['rateLimit'] ?? null;
    }

    /**
     * @return array{pattern: string, paramNames: list<string>, handler: callable, roles: list<string>, serviceContext: bool, rateLimit: ?RateLimitPolicy}|null
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
