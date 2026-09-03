<?php

declare(strict_types=1);

namespace App;

final class Application
{
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/app.php';

        date_default_timezone_set($this->config['timezone']);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}