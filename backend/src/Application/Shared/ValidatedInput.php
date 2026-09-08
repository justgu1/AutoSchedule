<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * É aqui que `mixed` morre, em vez de vazar da validação até a entidade.
 * Pedir campo que a regra não declarou é erro de programação, daí `LogicException`.
 */
final readonly class ValidatedInput
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    public function string(string $field): string
    {
        $value = $this->values[$field] ?? null;

        if (!is_string($value)) {
            throw new \LogicException(sprintf('Field "%s" was not validated as a string.', $field));
        }

        return $value;
    }

    public function stringOrNull(string $field): ?string
    {
        return ($this->values[$field] ?? null) === null ? null : $this->string($field);
    }

    public function stringOr(string $field, string $fallback): string
    {
        return $this->stringOrNull($field) ?? $fallback;
    }

    public function int(string $field): int
    {
        $value = $this->intOrNull($field);

        if ($value === null) {
            throw new \LogicException(sprintf('Field "%s" was not validated as a number.', $field));
        }

        return $value;
    }

    public function intOrNull(string $field): ?int
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && (!is_string($value) || !is_numeric($value))) {
            throw new \LogicException(sprintf('Field "%s" was not validated as a number.', $field));
        }

        return (int) $value;
    }

    /** JSON manda número, formulário manda string, e decimal só sobrevive inteiro como string. */
    public function decimalStringOrNull(string $field): ?string
    {
        $value = $this->values[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (!is_int($value) && !is_float($value)) {
            throw new \LogicException(sprintf('Field "%s" was not validated as a number.', $field));
        }

        return (string) $value;
    }

    public function boolOr(string $field, bool $fallback): bool
    {
        $value = $this->values[$field] ?? null;

        return is_bool($value) ? $value : $fallback;
    }

    /** @return list<string> */
    public function fields(): array
    {
        return array_keys($this->values);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
