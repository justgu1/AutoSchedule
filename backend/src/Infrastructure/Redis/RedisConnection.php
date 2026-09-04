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
    ) {
    }

    public function client(): Client
    {
        return $this->client ??= new Client(
            ['scheme' => 'tcp', 'host' => $this->host, 'port' => $this->port],
            $this->prefix !== '' ? ['prefix' => "{$this->prefix}:"] : [],
        );
    }
}
