<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

/**
 * Scheduler e worker rodam fora de qualquer request HTTP -- não tem
 * `current_user_id`/`current_user_role` pra setar, então as policies
 * admin-or-owner de `dealerships` (migration 000005) escondem toda linha
 * dessas conexões. Mesmo padrão que `users` já tinha desde o início
 * (`users_service_select`/`users_service_update`), só nunca foi replicado
 * aqui quando o domínio de concessionária ganhou rotina em background
 * (purge agendado, processamento assíncrono de foto).
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
