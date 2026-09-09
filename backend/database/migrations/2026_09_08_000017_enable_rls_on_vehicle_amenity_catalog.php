<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE vehicle_amenity_catalog ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_amenity_catalog FORCE ROW LEVEL SECURITY');

        // Catálogo global e estático: todo mundo lê, ninguém escreve pela API -- curadoria só por seeder,
        // que roda com `app.current_user_role` elevado a admin (`SeederRunner::run()`), nunca por serviço.
        $pdo->exec('CREATE POLICY vehicle_amenity_catalog_select ON vehicle_amenity_catalog FOR SELECT USING (true)');
        $pdo->exec(<<<'SQL'
            CREATE POLICY vehicle_amenity_catalog_admin_insert ON vehicle_amenity_catalog
            FOR INSERT WITH CHECK (current_setting('app.current_user_role', true) = 'admin')
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY vehicle_amenity_catalog_admin_insert ON vehicle_amenity_catalog');
        $pdo->exec('DROP POLICY vehicle_amenity_catalog_select ON vehicle_amenity_catalog');
        $pdo->exec('ALTER TABLE vehicle_amenity_catalog NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE vehicle_amenity_catalog DISABLE ROW LEVEL SECURITY');
    }
};
