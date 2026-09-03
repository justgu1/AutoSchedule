<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class HttpException extends \RuntimeException
{
    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self($message, 404);
    }

    public static function methodNotAllowed(string $message = 'Method Not Allowed'): self
    {
        return new self($message, 405);
    }
}
