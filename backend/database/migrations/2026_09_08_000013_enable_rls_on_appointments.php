<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE appointments ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE appointments FORCE ROW LEVEL SECURITY');

        // Dono transitivo via vehicle -> dealership, nunca delegado pro RLS do veículo (que também
        // libera leitura pública, e nome/e-mail/telefone do cliente são PII).
        $ownerPredicate = <<<'SQL'
            current_setting('app.current_user_role', true) = 'admin'
            OR EXISTS (
                SELECT 1 FROM vehicles v
                JOIN dealerships d ON d.id = v.dealership_id
                WHERE v.id = appointments.vehicle_id
                  AND d.owner_user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
            )
            SQL;

        $pdo->exec("CREATE POLICY appointments_admin_or_owner_select ON appointments FOR SELECT USING ({$ownerPredicate})");
        $pdo->exec("CREATE POLICY appointments_admin_or_owner_update ON appointments FOR UPDATE USING ({$ownerPredicate}) WITH CHECK ({$ownerPredicate})");

        $servicePredicate = "current_setting('app.is_service_context', true) = 'true'";

        // Serviço cobre três chamadores sem sessão de dono: a criação pública, a rotina agendada, e o
        // clique do cliente por token (a Application confere o token, a RLS só garante que dá pra gravar).
        $pdo->exec("CREATE POLICY appointments_service_insert ON appointments FOR INSERT WITH CHECK ({$servicePredicate})");
        $pdo->exec("CREATE POLICY appointments_service_select ON appointments FOR SELECT USING ({$servicePredicate})");
        $pdo->exec("CREATE POLICY appointments_service_update ON appointments FOR UPDATE USING ({$servicePredicate}) WITH CHECK ({$servicePredicate})");

        // O motor de disponibilidade roda anônimo e precisa saber o que já está ocupado. RLS libera
        // a linha (só pending/confirmed); a aplicação nunca seleciona PII nesse caminho público.
        $pdo->exec(<<<'SQL'
            CREATE POLICY appointments_public_select ON appointments
                FOR SELECT
                USING (
                    current_setting('app.is_public_read', true) = 'true'
                    AND status IN ('pending', 'confirmed')
                )
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY appointments_public_select ON appointments');
        $pdo->exec('DROP POLICY appointments_service_update ON appointments');
        $pdo->exec('DROP POLICY appointments_service_select ON appointments');
        $pdo->exec('DROP POLICY appointments_service_insert ON appointments');
        $pdo->exec('DROP POLICY appointments_admin_or_owner_update ON appointments');
        $pdo->exec('DROP POLICY appointments_admin_or_owner_select ON appointments');
        $pdo->exec('ALTER TABLE appointments NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE appointments DISABLE ROW LEVEL SECURITY');
    }
};
