<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

/**
 * Concessionária passa a ter só uma foto (substituível), não galeria -- dropa
 * `dealership_images` em favor de uma referência direta em `dealerships`.
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE dealership_images');

        $pdo->exec('ALTER TABLE dealerships ADD COLUMN photo_file_id uuid REFERENCES files(id) ON DELETE SET NULL');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships DROP COLUMN photo_file_id');

        $pdo->exec(<<<'SQL'
            CREATE TABLE dealership_images (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                dealership_id uuid NOT NULL REFERENCES dealerships(id) ON DELETE CASCADE,
                file_id uuid NOT NULL REFERENCES files(id),
                position smallint NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);
        $pdo->exec('CREATE UNIQUE INDEX dealership_images_position_unique ON dealership_images (dealership_id, position)');
    }
};
