<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * Existe porque o PDO não tem bind nativo de array. Só elemento simples, sem vírgula nem chave:
 * não é parser genérico, e quem chama só guarda valor que ele mesmo controla.
 */
final class PostgresArray
{
    /** @param list<string> $values */
    public static function toText(array $values): string
    {
        return '{' . implode(',', $values) . '}';
    }

    /** @return list<string> */
    public static function fromText(?string $raw): array
    {
        if ($raw === null || $raw === '{}') {
            return [];
        }

        return explode(',', trim($raw, '{}'));
    }
}
