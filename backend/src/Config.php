<?php

declare(strict_types=1);

namespace App;

final class Config
{
    /** Lista explícita, não `glob()`: dá pra saber o que a aplicação carrega sem rodar nada. */
    private const array FILES = [
        'app',
        'auth',
        'cors',
        'google',
        'mail',
        'pagination',
        'rate_limit',
        'redis',
        'security',
        'storage',
    ];

    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(?string $directory = null)
    {
        $directory ??= dirname(__DIR__) . '/config';

        foreach (self::FILES as $file) {
            $loaded = $this->load($directory . '/' . $file . '.php');

            // app.php é a raiz; os outros entram como grupo com o nome do arquivo.
            $this->values = $file === 'app' ? $loaded : [...$this->values, $file => $loaded];
        }

        date_default_timezone_set($this->string('timezone'));
    }

    /** Caminho pontuado (`auth.jwt.issuer`). Chave ausente ou de outro tipo é erro de ambiente: explode no boot. */
    public function string(string $path): string
    {
        $value = $this->value($path);

        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Config "%s" is not a string.', $path));
        }

        return $value;
    }

    public function stringOrNull(string $path): ?string
    {
        return $this->value($path) === null ? null : $this->string($path);
    }

    public function int(string $path): int
    {
        $value = $this->value($path);

        if (!is_int($value)) {
            throw new \RuntimeException(sprintf('Config "%s" is not an int.', $path));
        }

        return $value;
    }

    public function bool(string $path): bool
    {
        $value = $this->value($path);

        if (!is_bool($value)) {
            throw new \RuntimeException(sprintf('Config "%s" is not a bool.', $path));
        }

        return $value;
    }

    /** @return list<string> */
    public function stringList(string $path): array
    {
        $value = $this->value($path);

        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Config "%s" is not a list.', $path));
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private function value(string $path): mixed
    {
        $value = $this->values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function load(string $path): array
    {
        $values = require $path;

        if (!is_array($values)) {
            throw new \RuntimeException(sprintf('Config file "%s" must return an array.', $path));
        }

        return array_filter($values, is_string(...), ARRAY_FILTER_USE_KEY);
    }
}
