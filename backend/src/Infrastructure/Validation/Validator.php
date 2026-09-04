<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules field => pipe-separated rules, e.g. "required|email"
     * @return array<string, mixed> only the fields declared in $rules, as received (no coercion)
     *
     * @throws DomainException when any rule fails, with one message per field (first failure wins)
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleList) {
            $value = $data[$field] ?? null;

            foreach (explode('|', $ruleList) as $rule) {
                [$name, $parameter] = self::parseRule($rule);

                if (!self::passes($name, $value, $parameter)) {
                    $errors[$field] = self::message($field, $name, $parameter);

                    continue 2;
                }
            }

            if ($value !== null) {
                $validated[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, $errors);
        }

        return $validated;
    }

    /** @return array{0: string, 1: ?string} */
    private static function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private static function passes(string $rule, mixed $value, ?string $parameter): bool
    {
        // "required" is the only rule that must reject an absent value; every other
        // rule is skipped when the field is empty, so optional fields stay optional.
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return true;
        }

        return match ($rule) {
            'required' => $value !== null && $value !== '',
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            'min' => self::size($value) >= (float) $parameter,
            'max' => self::size($value) <= (float) $parameter,
            'in' => in_array((string) $value, explode(',', (string) $parameter), true),
            default => throw new \InvalidArgumentException("Unknown validation rule \"{$rule}\"."),
        };
    }

    private static function size(mixed $value): float
    {
        return is_string($value) ? (float) mb_strlen($value) : (float) $value;
    }

    private static function message(string $field, string $rule, ?string $parameter): string
    {
        return match ($rule) {
            'required' => "The {$field} field is required.",
            'email' => "The {$field} field must be a valid email address.",
            'uuid' => "The {$field} field must be a valid UUID.",
            'min' => "The {$field} field must be at least {$parameter}.",
            'max' => "The {$field} field must be at most {$parameter}.",
            'in' => "The {$field} field must be one of: {$parameter}.",
            default => "The {$field} field is invalid.",
        };
    }
}
