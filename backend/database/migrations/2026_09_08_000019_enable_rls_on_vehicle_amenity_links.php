<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE vehicle_amenity_links ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_amenity_links FORCE ROW LEVEL SECURITY');

        // Delega pro RLS do veículo, mesmo raciocínio de `vehicle_images`/`vehicle_availability_rules`.
        $visiblePredicate = <<<'SQL'
            EXISTS (SELECT 1 FROM vehicles v WHERE v.id = vehicle_amenity_links.vehicle_id)
            SQL;

        $pdo->exec("CREATE POLICY vehicle_amenity_links_select ON vehicle_amenity_links FOR SELECT USING ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_amenity_links_insert ON vehicle_amenity_links FOR INSERT WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_amenity_links_delete ON vehicle_amenity_links FOR DELETE USING ({$visiblePredicate})");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY vehicle_amenity_links_delete ON vehicle_amenity_links');
        $pdo->exec('DROP POLICY vehicle_amenity_links_insert ON vehicle_amenity_links');
        $pdo->exec('DROP POLICY vehicle_amenity_links_select ON vehicle_amenity_links');
        $pdo->exec('ALTER TABLE vehicle_amenity_links NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_amenity_links DISABLE ROW LEVEL SECURITY');
    }
};
