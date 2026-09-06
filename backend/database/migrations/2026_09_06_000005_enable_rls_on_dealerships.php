<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE dealerships FORCE ROW LEVEL SECURITY');

        $ownerPredicate = <<<'SQL'
            current_setting('app.current_user_role', true) = 'admin'
            OR owner_user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
            SQL;

        $pdo->exec("CREATE POLICY dealerships_admin_or_owner_select ON dealerships FOR SELECT USING ({$ownerPredicate})");
        $pdo->exec(<<<'SQL'
            CREATE POLICY dealerships_admin_or_seller_insert ON dealerships
                FOR INSERT
                WITH CHECK (current_setting('app.current_user_role', true) IN ('admin', 'seller'))
            SQL);
        $pdo->exec("CREATE POLICY dealerships_admin_or_owner_update ON dealerships FOR UPDATE USING ({$ownerPredicate}) WITH CHECK ({$ownerPredicate})");
        $pdo->exec("CREATE POLICY dealerships_admin_or_owner_delete ON dealerships FOR DELETE USING ({$ownerPredicate})");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY dealerships_admin_or_owner_select ON dealerships');
        $pdo->exec('DROP POLICY dealerships_admin_or_seller_insert ON dealerships');
        $pdo->exec('DROP POLICY dealerships_admin_or_owner_update ON dealerships');
        $pdo->exec('DROP POLICY dealerships_admin_or_owner_delete ON dealerships');
        $pdo->exec('ALTER TABLE dealerships NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE dealerships DISABLE ROW LEVEL SECURITY');
    }
};
