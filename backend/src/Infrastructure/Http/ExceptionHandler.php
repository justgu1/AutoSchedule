<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final class ExceptionHandler
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function handle(\Throwable $exception): Response
    {
        if ($exception instanceof HttpException) {
            return Response::error($exception->getMessage(), $exception->status());
        }

        if ($exception instanceof DomainException) {
            return match ($exception->type()) {
                DomainErrorType::NotFound => Response::error($exception->getMessage(), 404),
                DomainErrorType::Validation => Response::error($exception->getMessage(), 422, $exception->errors()),
                DomainErrorType::Conflict => Response::error($exception->getMessage(), 409),
            };
        }

        error_log((string) $exception);

        return Response::error(
            $this->debug ? $exception->getMessage() : 'Internal Server Error',
            500,
        );
    }
}
