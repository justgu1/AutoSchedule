<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\SeederRunner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Arquivo próprio porque a transação não commitada do SeederRunnerTest travaria a linha do admin
 * e este teste esperaria o lock pra sempre. Em produção o seed não roda como superuser, daí valer o teste.
 */
#[Group('integration')]
final class SeederRunnerRlsTest extends TestCase
{
    #[Test]
    public function run_nao_derruba_com_erro_de_rls_conectado_como_role_sem_bypass(): void
    {
        $rls = TestDatabase::connectAsApp()->pdo();

        $runner = new SeederRunner($rls, dirname(__DIR__, 3) . '/database/seeders');

        // Sem o SET LOCAL dentro do próprio SeederRunner, o INSERT do admin viola a policy de RLS.
        $executed = $runner->run();

        $this->assertContains('0001_create_admin_user', $executed);
    }
}
