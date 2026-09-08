<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Centavos inteiros: float perde centavo, e a coluna é `numeric(12,2)` justamente pra não perder. */
final readonly class Money implements \Stringable
{
    public function __construct(public int $cents)
    {
        if ($cents < 0) {
            throw self::invalid();
        }
    }

    public static function fromDecimal(string $value): self
    {
        $normalized = trim($value);

        if (preg_match('/^\d+(\.\d{1,2})?$/', $normalized) !== 1) {
            throw self::invalid();
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized), 2, '');

        return new self((int) $whole * 100 + (int) str_pad($fraction, 2, '0'));
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private static function invalid(): DomainException
    {
        return new DomainException('Invalid data.', DomainErrorType::Validation, [
            'price' => 'The price field must be a non-negative amount with at most two decimal places.',
        ]);
    }
}
