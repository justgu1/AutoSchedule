<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Normaliza porque o UNIQUE do banco é case-sensitive: sem isso `Ada@x.com` vira uma segunda conta. */
final readonly class Email implements \Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['email' => 'The email field must be a valid email address.']);
        }

        $this->value = $normalized;
    }

    /** Para o e-mail opcional, onde ausência é um valor legítimo e string vazia é o mesmo que ausência. */
    public static function fromNullable(?string $value): ?self
    {
        return $value === null || trim($value) === '' ? null : new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
