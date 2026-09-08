<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealership_availability_rules ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE dealership_availability_rules FORCE ROW LEVEL SECURITY');

        // Delega pro RLS da concessionária, sem repetir o predicado -- inclui a leitura pública, que
        // o motor de cálculo precisa pra rodar sem sessão. Escrita continua restrita a admin/seller.
        $visiblePredicate = <<<'SQL'
            EXISTS (SELECT 1 FROM dealerships d WHERE d.id = dealership_availability_rules.dealership_id)
            SQL;

        $pdo->exec("CREATE POLICY dealership_availability_rules_select ON dealership_availability_rules FOR SELECT USING ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY dealership_availability_rules_insert ON dealership_availability_rules FOR INSERT WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY dealership_availability_rules_update ON dealership_availability_rules FOR UPDATE USING ({$visiblePredicate}) WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY dealership_availability_rules_delete ON dealership_availability_rules FOR DELETE USING ({$visiblePredicate})");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY dealership_availability_rules_delete ON dealership_availability_rules');
        $pdo->exec('DROP POLICY dealership_availability_rules_update ON dealership_availability_rules');
        $pdo->exec('DROP POLICY dealership_availability_rules_insert ON dealership_availability_rules');
        $pdo->exec('DROP POLICY dealership_availability_rules_select ON dealership_availability_rules');
        $pdo->exec('ALTER TABLE dealership_availability_rules NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE dealership_availability_rules DISABLE ROW LEVEL SECURITY');
    }
};
