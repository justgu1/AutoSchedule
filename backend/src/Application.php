<?php

declare(strict_types=1);

namespace App;

final class Application
{
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/app.php';

        // Todo outro config/*.php é carregado como chave própria de topo (pelo
        // nome do arquivo), no mesmo formato que 'database' já é dentro do
        // próprio app.php.
        foreach (glob(dirname(__DIR__) . '/config/*.php') as $path) {
            $key = basename($path, '.php');

            if ($key !== 'app') {
                $this->config[$key] = require $path;
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

        date_default_timezone_set($this->config['timezone']);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
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
