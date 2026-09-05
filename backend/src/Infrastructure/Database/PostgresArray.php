<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * Codifica/decodifica valor `text[]` do Postgres pro PDO, que não tem bind
 * nativo de array. Só lida com elemento simples, sem vírgula/chave (slug de
 * grant type, scope, URI) -- não é um parser genérico pra literal de array
 * com aspas/escape, já que quem chama só guarda valor que ele mesmo controla.
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
