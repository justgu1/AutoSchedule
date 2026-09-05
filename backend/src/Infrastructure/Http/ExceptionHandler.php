<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Logging\Logger;
use Psr\Log\LoggerInterface;

final readonly class ExceptionHandler
{
    public function __construct(
        private bool $debug = false,
        private LoggerInterface $logger = new Logger(),
    ) {
    }

    public function handle(\Throwable $exception): Response
    {
        if ($exception instanceof HttpException) {
            return Response::error($exception->getMessage(), $exception->status());
        }

        if ($exception instanceof \JsonException) {
            return Response::error('Malformed JSON body.', 422);
        }

        if ($exception instanceof DomainException) {
            return match ($exception->type()) {
                DomainErrorType::NotFound => Response::error($exception->getMessage(), 404),
                DomainErrorType::Validation => Response::error($exception->getMessage(), 422, $exception->errors()),
                DomainErrorType::Conflict => Response::error($exception->getMessage(), 409),
                DomainErrorType::Unauthorized => Response::error($exception->getMessage(), 401),
                DomainErrorType::Forbidden => Response::error($exception->getMessage(), 403),
            };
        }

        $this->logger->error((string) $exception);

        return Response::error(
            $this->debug ? $exception->getMessage() : 'Internal Server Error',
            500,
        );
    }
}
