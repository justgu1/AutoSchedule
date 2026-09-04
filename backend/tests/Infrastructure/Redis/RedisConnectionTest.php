<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Redis;

use App\Infrastructure\Redis\RedisConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Redis real do docker-compose. Precisa
 * rodar dentro da rede `autoschedule` (via `make test` /
 * `docker compose exec backend vendor/bin/phpunit`) — não alcança o
 * hostname `redis` a partir de um container avulso fora do compose.
 */
final class RedisConnectionTest extends TestCase
{
    #[Test]
    public function conecta_e_executa_um_comando_real(): void
    {
        $this->assertSame('PONG', (string) $this->makeConnection()->client()->ping());
    }

    #[Test]
    public function client_e_lazy_e_devolve_sempre_a_mesma_instancia(): void
    {
        $connection = $this->makeConnection();

        $this->assertSame($connection->client(), $connection->client());
    }

    #[Test]
    public function aplica_o_prefixo_configurado_as_chaves(): void
    {
        $connection = $this->makeConnection(prefix: 'test-prefix');
        $connection->client()->set('some-key', 'some-value');

        try {
            $this->assertSame('some-value', $connection->client()->get('some-key'));
            $this->assertSame('some-value', $connection->client()->getConnection()->executeCommand(
                \Predis\Command\RawCommand::create('GET', 'test-prefix:some-key'),
            ));
        } finally {
            $connection->client()->del('some-key');
        }
    }

    private function makeConnection(string $prefix = 'autoschedule'): RedisConnection
    {
        return new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
            prefix: $prefix,
        );
    }
}
