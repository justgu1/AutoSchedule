<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Application\Shared\ValidatedInput;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Uf;

final class Validator
{
    /**
     * @param mixed $data corpo cru da request -- qualquer coisa que não seja um array vira "nenhum campo enviado"
     * @param array<string, string> $rules campo => regras separadas por "|", ex. "required|email"
     *
     * @throws DomainException quando alguma regra falha, uma mensagem por campo (primeira falha vence)
     */
    public static function validate(mixed $data, array $rules): ValidatedInput
    {
        $data = is_array($data) ? $data : [];
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

        return new ValidatedInput($validated);
    }

    /** @return array{0: string, 1: ?string} */
    private static function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private static function passes(string $rule, mixed $value, ?string $parameter): bool
    {
        // "required" é a única regra que rejeita valor ausente; toda outra regra
        // é pulada quando o campo está vazio, então campo opcional continua opcional.
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return true;
        }

        return match ($rule) {
            'required' => $value !== null && $value !== '',
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            // Barra aqui o que `Uf::from()` transformaria em ValueError -- 500 no lugar de 422.
            'uf' => is_string($value) && Uf::tryFrom($value) instanceof Uf,
            'min' => self::size($value) >= (float) $parameter,
            'max' => self::size($value) <= (float) $parameter,
            'in' => in_array((string) $value, explode(',', (string) $parameter), true),
            'numeric' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            // Lê o valor, não o tamanho, ao contrário de min/max -- por isso é regra separada e não parâmetro delas.
            'between' => self::withinRange($value, $parameter),
            default => throw new \InvalidArgumentException("Unknown validation rule \"{$rule}\"."),
        };
    }

    private static function size(mixed $value): float
    {
        return is_string($value) ? (float) mb_strlen($value) : (float) $value;
    }

    private static function withinRange(mixed $value, ?string $parameter): bool
    {
        if (!is_int($value) && !is_float($value) && (!is_string($value) || !is_numeric($value))) {
            return false;
        }

        [$min, $max] = array_pad(explode(',', (string) $parameter, 2), 2, null);

        return (float) $value >= (float) $min && (float) $value <= (float) $max;
    }

    private static function message(string $field, string $rule, ?string $parameter): string
    {
        return match ($rule) {
            'required' => "The {$field} field is required.",
            'email' => "The {$field} field must be a valid email address.",
            'uuid' => "The {$field} field must be a valid UUID.",
            'uf' => "The {$field} field must be a valid Brazilian state code.",
            'min' => "The {$field} field must be at least {$parameter}.",
            'max' => "The {$field} field must be at most {$parameter}.",
            'in' => "The {$field} field must be one of: {$parameter}.",
            'numeric' => "The {$field} field must be a number.",
            'between' => "The {$field} field must be between " . str_replace(',', ' and ', (string) $parameter) . '.',
            default => "The {$field} field is invalid.",
        };
    }
}
