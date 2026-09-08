<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE vehicle_availability_rules ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_availability_rules FORCE ROW LEVEL SECURITY');

        // Delega pro RLS do veículo, mesmo raciocínio de `vehicle_images`.
        $visiblePredicate = <<<'SQL'
            EXISTS (SELECT 1 FROM vehicles v WHERE v.id = vehicle_availability_rules.vehicle_id)
            SQL;

        $pdo->exec("CREATE POLICY vehicle_availability_rules_select ON vehicle_availability_rules FOR SELECT USING ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_availability_rules_insert ON vehicle_availability_rules FOR INSERT WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_availability_rules_update ON vehicle_availability_rules FOR UPDATE USING ({$visiblePredicate}) WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_availability_rules_delete ON vehicle_availability_rules FOR DELETE USING ({$visiblePredicate})");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY vehicle_availability_rules_delete ON vehicle_availability_rules');
        $pdo->exec('DROP POLICY vehicle_availability_rules_update ON vehicle_availability_rules');
        $pdo->exec('DROP POLICY vehicle_availability_rules_insert ON vehicle_availability_rules');
        $pdo->exec('DROP POLICY vehicle_availability_rules_select ON vehicle_availability_rules');
        $pdo->exec('ALTER TABLE vehicle_availability_rules NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_availability_rules DISABLE ROW LEVEL SECURITY');
    }
};
