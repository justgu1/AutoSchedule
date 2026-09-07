<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Infrastructure\Persistence\PostgresConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

#[Group('integration')]
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
        return TestDatabase::connect();
    }
}
