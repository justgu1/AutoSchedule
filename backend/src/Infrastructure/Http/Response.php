<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        protected readonly string $body = '',
        protected readonly int $status = 200,
        protected array $headers = [],
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

    public function body(): string
    {
        return $this->body;
    }

    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $data], $status);
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

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
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
