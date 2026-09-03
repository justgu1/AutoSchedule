<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Precisa
 * rodar dentro da rede `autoschedule` (via `make test` /
 * `docker compose exec backend vendor/bin/phpunit`) — não alcança o
 * hostname `postgres` a partir de um container avulso fora do compose.
 */
final class PostgresConnectionTest extends TestCase
{
    #[Test]
    public function conecta_e_executa_uma_query_real(): void
    {
        $result = $this->makeConnection()->pdo()->query('SELECT 1')->fetchColumn();

        // PDO_PGSQL devolve o valor como string ("1");
        $this->assertEquals(1, $result);
    }

    #[Test]
    public function pdo_e_lazy_e_devolve_sempre_a_mesma_instancia(): void
    {
        $connection = $this->makeConnection();

        $this->assertSame($connection->pdo(), $connection->pdo());
    }

    private function makeConnection(): PostgresConnection
    {
        return new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_USERNAME') ?: 'pgsql',
            password: getenv('DB_PASSWORD') ?: 'password',
        );
    }
}
