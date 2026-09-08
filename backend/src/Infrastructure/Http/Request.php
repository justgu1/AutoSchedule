<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Request
{
    private readonly string $path;

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     * @param array<string, string> $params
     * @param array<string, mixed> $attributes
     * @param array<string, string> $cookies
     * @param array<string, list<UploadedFile>> $files
     */
    public function __construct(
        private readonly string $method,
        string $path,
        private readonly array $headers = [],
        private readonly array $query = [],
        private readonly string $body = '',
        private array $params = [],
        private readonly string $ip = '',
        private array $attributes = [],
        private readonly array $cookies = [],
        private readonly array $files = [],
    ) {
        // No construtor e não em `path()`: assim toda comparação de rota parte da mesma forma.
        $this->path = self::normalizePath($path);
    }

    public static function fromGlobals(?string $rawBody = null): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? HttpMethod::Get->value));

        if ($rawBody === null && (HttpMethod::tryFrom($method)?->hasBody() ?? false)) {
            $rawBody = (string) file_get_contents('php://input');
        }

        return new self(
            method: $method,
            path: self::resolvePath((string) ($_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/')),
            headers: self::resolveHeaders(),
            query: $_GET,
            body: $rawBody ?? '',
            ip: (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            cookies: $_COOKIE,
            files: self::resolveFiles(),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function query(string $name, ?string $default = null): ?string
    {
        return $this->query[$name] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @throws \JsonException quando o corpo não é um JSON válido
     */
    public function json(): mixed
    {
        return json_decode($this->body, associative: true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Corpo JSON como mapa de campos -- o que não for objeto JSON (lista,
     * escalar, corpo vazio) vira "nenhum campo enviado".
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException quando o corpo não é um JSON válido
     */
    public function jsonFields(): array
    {
        $decoded = $this->json();

        if (!is_array($decoded)) {
            return [];
        }

        return array_filter($decoded, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    public function param(string $name, ?string $default = null): ?string
    {
        return $this->params[$name] ?? $default;
    }

    /** @return array<string, string> */
    public function params(): array
    {
        return $this->params;
    }

    /** @param array<string, string> $params */
    #[\NoDiscard]
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;

        return $clone;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function file(string $name): ?UploadedFile
    {
        return $this->files[$name][0] ?? null;
    }

    /** @return list<UploadedFile> */
    public function files(string $name): array
    {
        return $this->files[$name] ?? [];
    }

    /** Header vence o cookie: quem manda o Bearer na mão está dizendo que não depende de credencial ambiente. */
    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $this->cookie(Cookie::ACCESS_TOKEN);
    }

    public function usesCookieAuth(): bool
    {
        return $this->header('authorization') === null && $this->cookie(Cookie::ACCESS_TOKEN) !== null;
    }

    /** Casada uma vez por `ResolveRouteMiddleware`; null quando nenhuma rota bate. */
    public function route(): ?Route
    {
        $route = $this->attributes['route'] ?? null;

        return $route instanceof Route ? $route : null;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    #[\NoDiscard]
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    private static function resolvePath(string $path): string
    {
        $path = strtok($path, '?');

        return $path === false || $path === '' ? '/' : $path;
    }

    public static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/');
    }

    /** @return array<string, string> */
    private static function resolveHeaders(): array
    {
        if (function_exists('getallheaders')) {
            /** @var array<string, string> $headers */
            $headers = getallheaders();

            return array_change_key_case($headers, CASE_LOWER);
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_') && is_string($value)) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Campo `nome[]` faz o PHP entregar array em cada chave em vez de escalar, então tudo vira lista
     * e o campo simples é a lista de um.
     *
     * @return array<string, list<UploadedFile>>
     */
    private static function resolveFiles(): array
    {
        $files = [];

        foreach ($_FILES as $name => $file) {
            if (!is_string($name) || !is_array($file)) {
                continue;
            }

            $tmpNames = is_array($file['tmp_name'] ?? null) ? array_values($file['tmp_name']) : [$file['tmp_name'] ?? ''];

            foreach ($tmpNames as $index => $tmpName) {
                $files[$name][] = new UploadedFile(
                    tmpName: (string) $tmpName,
                    originalName: (string) self::fileValue($file, 'name', $index),
                    size: (int) self::fileValue($file, 'size', $index),
                    error: (int) (self::fileValue($file, 'error', $index) ?? UPLOAD_ERR_NO_FILE),
                );
            }
        }

        return $files;
    }

    /** @param array<mixed> $file */
    private static function fileValue(array $file, string $key, int $index): mixed
    {
        $value = $file[$key] ?? null;

        return is_array($value) ? (array_values($value)[$index] ?? null) : $value;
    }
}
