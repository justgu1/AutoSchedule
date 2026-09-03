<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class DomainException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        string $message,
        private readonly DomainErrorType $type,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function type(): DomainErrorType
    {
        return $this->type;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
