<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Catálogo GLOBAL, não por concessionária -- sem dealership_id, sem trash, sem status.
        $pdo->exec(<<<'SQL'
            CREATE TABLE vehicle_amenity_catalog (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                code varchar(60) NOT NULL,
                label varchar(80) NOT NULL,
                position smallint NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE UNIQUE INDEX vehicle_amenity_catalog_code_unique ON vehicle_amenity_catalog (code)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE vehicle_amenity_catalog');
    }
};
