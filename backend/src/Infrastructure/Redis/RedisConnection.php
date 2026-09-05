<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Predis\Client;

final class RedisConnection
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $prefix = '',
        private readonly ?string $username = null,
        private readonly ?string $password = null,
    ) {
    }

    public function client(): Client
    {
        // Redis local (dev) não pede auth -- username/password só entram nos
        // parâmetros de conexão quando informados (Redis com ACL, ex: cluster).
        $parameters = ['scheme' => 'tcp', 'host' => $this->host, 'port' => $this->port];

        if ($this->username !== null) {
            $parameters['username'] = $this->username;
        }

        if ($this->password !== null) {
            $parameters['password'] = $this->password;
        }

        return $this->client ??= new Client(
            $parameters,
            $this->prefix !== '' ? ['prefix' => "{$this->prefix}:"] : [],
        );
    }
}
