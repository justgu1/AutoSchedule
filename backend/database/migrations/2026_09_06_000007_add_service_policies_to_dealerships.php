<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Migration;

/**
 * Sem request HTTP não há identidade pra setar, então as policies admin-or-owner escondem tudo do background.
 * `users` já tinha o equivalente; faltou replicar aqui quando concessionária ganhou rotina assíncrona.
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE POLICY dealerships_service_select ON dealerships
                FOR SELECT
                USING (current_setting('app.is_service_context', true) = 'true')
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE POLICY dealerships_service_update ON dealerships
                FOR UPDATE
                USING (current_setting('app.is_service_context', true) = 'true')
                WITH CHECK (current_setting('app.is_service_context', true) = 'true')
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY IF EXISTS dealerships_service_update ON dealerships');
        $pdo->exec('DROP POLICY IF EXISTS dealerships_service_select ON dealerships');
    }
};
