<?php

declare(strict_types=1);

namespace App;

/** Leitura de variável de ambiente com tipo e default, usada só pelos arquivos de `config/`. */
final class Env
{
    public static function string(string $name, string $default): string
    {
        return self::raw($name) ?? $default;
    }

    public static function stringOrNull(string $name): ?string
    {
        return self::raw($name);
    }

    public static function int(string $name, int $default): int
    {
        $value = self::raw($name);

        return $value === null ? $default : (int) $value;
    }

    public static function bool(string $name, bool $default): bool
    {
        $value = self::raw($name);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** Secret selado (SealedSecret/kubeseal) costuma chegar com `\n` sobrando, e já derrubou login em produção. */
    private static function raw(string $name): ?string
    {
        $value = getenv($name);

        if ($value === false) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
