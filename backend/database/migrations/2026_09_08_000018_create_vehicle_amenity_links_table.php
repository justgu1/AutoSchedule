<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE vehicle_amenity_links (
                vehicle_id uuid NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
                amenity_id uuid NOT NULL REFERENCES vehicle_amenity_catalog(id),
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (vehicle_id, amenity_id)
            )
            SQL);

        $pdo->exec('CREATE INDEX vehicle_amenity_links_amenity_id_idx ON vehicle_amenity_links (amenity_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE vehicle_amenity_links');
    }
};
