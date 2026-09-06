<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Database\SeederRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração isolado em arquivo próprio (não em SeederRunnerTest)
 * porque conecta como `autoschedule_app` numa transação separada -- se
 * dividisse a classe com SeederRunnerTest, a transação (não commitada) do
 * setUp() de lá deixaria a linha do admin travada e este teste ficaria
 * esperando o lock pra sempre.
 *
 * Regressão: em produção quem roda o seed não é superuser (diferente do
 * `pgsql` local), então fica sujeito de verdade à RLS de `users`. Conecta
 * como `autoschedule_app` (NOSUPERUSER NOBYPASSRLS, igual RlsPolicyTest) pra
 * provar que o SeederRunner cobre o próprio contexto -- sem o `SET LOCAL`
 * isso derruba com "new row violates row-level security policy".
 */
final class SeederRunnerRlsTest extends TestCase
{
    #[Test]
    public function run_nao_derruba_com_erro_de_rls_conectado_como_role_sem_bypass(): void
    {
        $rls = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_APP_USERNAME') ?: 'autoschedule_app',
            password: getenv('DB_APP_PASSWORD') ?: 'changeme',
        )->pdo();

        $runner = new SeederRunner($rls, dirname(__DIR__, 3) . '/database/seeders');

        // Sem o `SET LOCAL app.current_user_role = 'admin'` dentro do
        // próprio SeederRunner, o INSERT do admin derruba com
        // "new row violates row-level security policy for table users".
        $executed = $runner->run();

        $this->assertContains('0001_create_admin_user', $executed);
    }
}
