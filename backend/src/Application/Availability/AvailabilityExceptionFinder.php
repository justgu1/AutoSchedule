<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** "Não é sua" e "não existe" viram o mesmo 404 -- o RLS (delegado pro dono do escopo) já esconde a linha alheia. */
final readonly class AvailabilityExceptionFinder
{
    public function __construct(private AvailabilityExceptionRepository $exceptions)
    {
    }

    public function findOrFail(?string $id): AvailabilityException
    {
        $exception = $id !== null ? $this->exceptions->findById($id) : null;

        if (!$exception instanceof AvailabilityException) {
            throw new DomainException('Availability exception not found.', DomainErrorType::NotFound);
        }

        return $exception;
    }
}
