<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\User\UserRole;

/**
 * Mutável de propósito: as rotas são montadas no boot e só lidas depois, e é o que deixa
 * `$router->get(...)->roles(...)->describes(...)` funcionar sem builder no meio.
 */
final class Route
{
    /** @var list<string> */
    private array $paramNames = [];

    /** Compilado só quando a rota é de fato testada, e só quando tem parâmetro. */
    private ?string $pattern = null;

    /** @var list<UserRole> */
    private array $roles = [];

    private bool $serviceContext = false;

    private bool $publicRead = false;

    private string $description = '';

    /** @var list<string> */
    private array $accepts = [];

    private ?string $rateLimitPolicy = null;

    /** @param array{class-string, string}|\Closure(Request): Response $handler */
    public function __construct(
        public readonly HttpMethod $method,
        public readonly string $path,
        private readonly array|\Closure $handler,
    ) {
    }

    public function roles(UserRole ...$roles): self
    {
        $this->roles = array_values($roles);

        return $this;
    }

    /** Marca a rota que precisa mexer no banco antes de existir identidade, como o login. */
    public function serviceContext(): self
    {
        $this->serviceContext = true;

        return $this;
    }

    /** Marca a rota que responde também a quem não é dono nem admin, com um perfil reduzido. */
    public function publicRead(): self
    {
        $this->publicRead = true;

        return $this;
    }

    public function describes(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** Campos aceitos no corpo -- documentação do catálogo, não validação: quem valida é o Validator. */
    public function accepts(string ...$fields): self
    {
        $this->accepts = array_values($fields);

        return $this;
    }

    /** Nome da policy em `config/rate_limit.php`; sem isso vale a policy geral do pipeline. */
    public function rateLimit(string $policy): self
    {
        $this->rateLimitPolicy = $policy;

        return $this;
    }

    /** @return array<string, string>|null parâmetros da URL, ou null quando a rota não bate */
    public function match(string $path): ?array
    {
        if (!str_contains($this->path, '{')) {
            return $this->path === $path ? [] : null;
        }

        if (preg_match($this->compile(), $path, $matches) !== 1) {
            return null;
        }

        $params = [];

        foreach ($this->paramNames as $name) {
            $params[$name] = $matches[$name];
        }

        return $params;
    }

    /** @return list<UserRole> */
    public function requiredRoles(): array
    {
        return $this->roles;
    }

    public function isPublic(): bool
    {
        return $this->roles === [];
    }

    public function needsServiceContext(): bool
    {
        return $this->serviceContext;
    }

    public function allowsPublicRead(): bool
    {
        return $this->publicRead;
    }

    public function rateLimitPolicy(): ?string
    {
        return $this->rateLimitPolicy;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** @return list<string> */
    public function acceptedFields(): array
    {
        return $this->accepts;
    }

    /** @return array{class-string, string}|\Closure(Request): Response */
    public function handler(): array|\Closure
    {
        return $this->handler;
    }

    /**
     * Separa literais de `{param}` mantendo os delimitadores, pra escapar só o literal e não as chaves.
     */
    private function compile(): string
    {
        if ($this->pattern !== null) {
            return $this->pattern;
        }

        $parts = preg_split('#(\{[a-zA-Z_][a-zA-Z0-9_]*\})#', $this->path, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $pattern = '';

        foreach ($parts as $part) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$#', $part, $matches) === 1) {
                $this->paramNames[] = $matches[1];
                $pattern .= '(?P<' . $matches[1] . '>[^/]+)';

                continue;
            }

            $pattern .= preg_quote($part, '#');
        }

        return $this->pattern = '#^' . $pattern . '$#';
    }
}
