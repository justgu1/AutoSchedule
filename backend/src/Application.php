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

        date_default_timezone_set($this->config['timezone']);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}