<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class Response
{
    /**
     * @param array<string, string> $headers
     * @param array<string, Cookie> $cookies
     */
    public function __construct(
        protected readonly string $body = '',
        protected readonly int $status = 200,
        protected array $headers = [],
        protected array $cookies = [],
    ) {
        foreach ($this->headers as $name => $value) {
            $this->assertSafeHeader($name, $value);
        }
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return array<string, Cookie> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function body(): string
    {
        return $this->body;
    }

    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $data], $status);
    }

    /** @param list<mixed> $data */
    public static function paginated(array $data, int $page, int $perPage, int $total): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    /** @param array<string, string> $errors */
    public static function error(string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse($payload, $status);
    }

    public function withHeader(string $name, string $value): static
    {
        $this->assertSafeHeader($name, $value);

        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function withCookie(
        string $name,
        string $value,
        int $maxAge = 0,
        bool $httpOnly = true,
        SameSite $sameSite = SameSite::Strict,
        bool $secure = false,
    ): static {
        $clone = clone $this;
        $clone->cookies[$name] = new Cookie($value, $maxAge, $httpOnly, $sameSite, $secure);

        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        foreach ($this->cookies as $name => $cookie) {
            setcookie($name, $cookie->value, [
                'expires' => $cookie->expiresAt(),
                'path' => '/',
                'httponly' => $cookie->httpOnly,
                'samesite' => $cookie->sameSite->value,
                'secure' => $cookie->secure,
            ]);
        }

        echo $this->body;
    }

    /**
     * Barra header injection (CRLF) explicitamente — o `header()` do PHP já
     * recusa isso sozinho, mas falhar aqui é mais cedo e mais claro.
     */
    private function assertSafeHeader(string $name, string $value): void
    {
        if (preg_match('/[\r\n]/', $name . $value) === 1) {
            throw new \InvalidArgumentException('Invalid header: line breaks are not allowed.');
        }
    }
}
