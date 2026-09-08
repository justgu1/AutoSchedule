<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE vehicles ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicles FORCE ROW LEVEL SECURITY');

        // O dono do veículo é transitivo: quem manda é o `owner_user_id` da concessionária, nunca uma cópia aqui.
        $ownerPredicate = <<<'SQL'
            current_setting('app.current_user_role', true) = 'admin'
            OR EXISTS (
                SELECT 1 FROM dealerships d
                WHERE d.id = vehicles.dealership_id
                  AND d.owner_user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
            )
            SQL;

        $pdo->exec("CREATE POLICY vehicles_admin_or_owner_select ON vehicles FOR SELECT USING ({$ownerPredicate})");

        // Diferente da concessionária, onde basta a role: aqui o dono vem do payload, então quem insere precisa provar que é dele.
        $pdo->exec(<<<SQL
            CREATE POLICY vehicles_admin_or_owner_insert ON vehicles
                FOR INSERT
                WITH CHECK (
                    current_setting('app.current_user_role', true) IN ('admin', 'seller')
                    AND ({$ownerPredicate})
                )
            SQL);

        $pdo->exec("CREATE POLICY vehicles_admin_or_owner_update ON vehicles FOR UPDATE USING ({$ownerPredicate}) WITH CHECK ({$ownerPredicate})");
        $pdo->exec("CREATE POLICY vehicles_admin_or_owner_delete ON vehicles FOR DELETE USING ({$ownerPredicate})");

        $servicePredicate = "current_setting('app.is_service_context', true) = 'true'";

        $pdo->exec("CREATE POLICY vehicles_service_select ON vehicles FOR SELECT USING ({$servicePredicate})");
        $pdo->exec("CREATE POLICY vehicles_service_update ON vehicles FOR UPDATE USING ({$servicePredicate}) WITH CHECK ({$servicePredicate})");

        // O `d.status` repetido é redundante com a policy pública de concessionária, mas condição de segurança não se apoia em policy aninhada.
        $pdo->exec(<<<'SQL'
            CREATE POLICY vehicles_public_select ON vehicles
                FOR SELECT
                USING (
                    current_setting('app.is_public_read', true) = 'true'
                    AND status = 'active'
                    AND EXISTS (
                        SELECT 1 FROM dealerships d
                        WHERE d.id = vehicles.dealership_id AND d.status = 'active'
                    )
                )
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY vehicles_public_select ON vehicles');
        $pdo->exec('DROP POLICY vehicles_service_update ON vehicles');
        $pdo->exec('DROP POLICY vehicles_service_select ON vehicles');
        $pdo->exec('DROP POLICY vehicles_admin_or_owner_delete ON vehicles');
        $pdo->exec('DROP POLICY vehicles_admin_or_owner_update ON vehicles');
        $pdo->exec('DROP POLICY vehicles_admin_or_owner_insert ON vehicles');
        $pdo->exec('DROP POLICY vehicles_admin_or_owner_select ON vehicles');
        $pdo->exec('ALTER TABLE vehicles NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicles DISABLE ROW LEVEL SECURITY');
    }
};
