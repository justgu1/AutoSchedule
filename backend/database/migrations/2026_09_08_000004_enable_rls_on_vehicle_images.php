<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE vehicle_images ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_images FORCE ROW LEVEL SECURITY');

        // Delega pro RLS do veículo: a imagem enxerga exatamente quem enxerga a linha dona, sem repetir o predicado.
        $visiblePredicate = <<<'SQL'
            EXISTS (SELECT 1 FROM vehicles v WHERE v.id = vehicle_images.vehicle_id)
            SQL;

        $pdo->exec("CREATE POLICY vehicle_images_select ON vehicle_images FOR SELECT USING ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_images_insert ON vehicle_images FOR INSERT WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_images_update ON vehicle_images FOR UPDATE USING ({$visiblePredicate}) WITH CHECK ({$visiblePredicate})");
        $pdo->exec("CREATE POLICY vehicle_images_delete ON vehicle_images FOR DELETE USING ({$visiblePredicate})");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY vehicle_images_delete ON vehicle_images');
        $pdo->exec('DROP POLICY vehicle_images_update ON vehicle_images');
        $pdo->exec('DROP POLICY vehicle_images_insert ON vehicle_images');
        $pdo->exec('DROP POLICY vehicle_images_select ON vehicle_images');
        $pdo->exec('ALTER TABLE vehicle_images NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_images DISABLE ROW LEVEL SECURITY');
    }
};
