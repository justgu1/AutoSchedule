<?php

declare(strict_types=1);

namespace App;

final class Config
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->config = $this->load(dirname(__DIR__) . '/config/app.php');

        // Todo outro config/*.php é carregado como chave própria de topo (pelo
        // nome do arquivo), no mesmo formato que 'database' já é dentro do
        // próprio app.php.
        foreach (glob(dirname(__DIR__) . '/config/*.php') ?: [] as $path) {
            $key = basename($path, '.php');

            if ($key !== 'app') {
                $this->config[$key] = $this->load($path);
            }
        }

        // Secrets selados (SealedSecret/kubeseal) e outras fontes de env
        // costumam carregar um `\n` sobrando quando o valor original foi
        // gerado com `echo` em vez de `printf`/`echo -n`. Em vez de depender
        // de cada fonte de env estar sempre byte-perfeita, corta espaço em
        // branco nas bordas de todo valor de config aqui, uma vez só --
        // string comparada/parseada contra um valor externo limpo (ex: aud
        // claim de um JWT, DSN do mailer) nunca mais quebra por causa disso.
        $this->config = $this->trimStrings($this->config);

        date_default_timezone_set($this->string('timezone'));
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $path): array
    {
        $values = require $path;

        if (!is_array($values)) {
            throw new \RuntimeException(sprintf('Config file "%s" must return an array.', $path));
        }

        // Chave numérica não é config nomeada -- descartar aqui é o que mantém
        // o mapa tipado sem precisar confiar no arquivo.
        return array_filter($values, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Acesso tipado por caminho pontuado (`auth.jwt.issuer`). Chave ausente ou
     * com tipo diferente do pedido é erro de configuração do ambiente, não algo
     * pra tratar em runtime -- explode no boot, não numa request qualquer.
     */
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

    private function value(string $path): mixed
    {
        $value = $this->config;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @template TKey of array-key
     * @param array<TKey, mixed> $config
     * @return array<TKey, mixed>
     */
    private function trimStrings(array $config): array
    {
        return array_map(
            fn (mixed $value): mixed => match (true) {
                is_string($value) => trim($value),
                is_array($value) => $this->trimStrings($value),
                default => $value,
            },
            $config,
        );
    }
}
