<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class Response
{
    /**
     * @param array<string, string> $headers
     * @param array<string, array{value: string, maxAge: int, httpOnly: bool, sameSite: string, secure: bool}> $cookies
     */
    public function __construct(
        protected readonly string $body = '',
        protected readonly int $status = 200,
        protected array $headers = [],
        protected array $cookies = [],
    ) {
        foreach ($this->headers as $name => $value) {
            self::assertSafeHeader($name, $value);
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

    /** @return array<string, array{value: string, maxAge: int, httpOnly: bool, sameSite: string, secure: bool}> */
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
        self::assertSafeHeader($name, $value);

        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /** $maxAge em segundos; 0 = cookie de sessão (some ao fechar o browser); negativo = apaga o cookie (logout). */
    public function withCookie(
        string $name,
        string $value,
        int $maxAge = 0,
        bool $httpOnly = true,
        string $sameSite = 'Strict',
        bool $secure = false,
    ): static {
        $clone = clone $this;
        $clone->cookies[$name] = [
            'value' => $value,
            'maxAge' => $maxAge,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite,
            'secure' => $secure,
        ];

        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        foreach ($this->cookies as $name => $cookie) {
            $expires = match (true) {
                $cookie['maxAge'] > 0 => time() + $cookie['maxAge'],
                $cookie['maxAge'] < 0 => time() - 3600,
                default => 0,
            };

            setcookie($name, $cookie['value'], [
                'expires' => $expires,
                'path' => '/',
                'httponly' => $cookie['httpOnly'],
                'samesite' => $cookie['sameSite'],
                'secure' => $cookie['secure'],
            ]);
        }

        echo $this->body;
    }

    /**
     * Barra header injection (CRLF) explicitamente — o `header()` do PHP já
     * recusa isso sozinho, mas falhar aqui é mais cedo e mais claro.
     */
    private static function assertSafeHeader(string $name, string $value): void
    {
        if (preg_match('/[\r\n]/', $name . $value) === 1) {
            throw new \InvalidArgumentException('Invalid header: line breaks are not allowed.');
        }
    }
}
