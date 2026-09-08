<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE availability_exceptions ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE availability_exceptions FORCE ROW LEVEL SECURITY');

        // A linha é ou de concessionária ou de veículo (nunca as duas): delega pro RLS de quem
        // estiver preenchido, mesmo raciocínio de `vehicle_availability_rules`.
        $visiblePredicate = <<<'SQL'
            (dealership_id IS NOT NULL AND EXISTS (SELECT 1 FROM dealerships d WHERE d.id = availability_exceptions.dealership_id))
            OR (vehicle_id IS NOT NULL AND EXISTS (SELECT 1 FROM vehicles v WHERE v.id = availability_exceptions.vehicle_id))
            SQL;

        $pdo->exec("CREATE POLICY availability_exceptions_select ON availability_exceptions FOR SELECT USING ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY availability_exceptions_insert ON availability_exceptions FOR INSERT WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY availability_exceptions_update ON availability_exceptions FOR UPDATE USING ({$visiblePredicate}) WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY availability_exceptions_delete ON availability_exceptions FOR DELETE USING ({$visiblePredicate})");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY availability_exceptions_delete ON availability_exceptions');
        $pdo->exec('DROP POLICY availability_exceptions_update ON availability_exceptions');
        $pdo->exec('DROP POLICY availability_exceptions_insert ON availability_exceptions');
        $pdo->exec('DROP POLICY availability_exceptions_select ON availability_exceptions');
        $pdo->exec('ALTER TABLE availability_exceptions NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE availability_exceptions DISABLE ROW LEVEL SECURITY');
    }
};
