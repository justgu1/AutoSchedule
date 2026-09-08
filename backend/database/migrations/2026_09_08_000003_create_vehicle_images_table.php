<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Sem CHECK de posição não negativa: a reordenação passa por uma faixa negativa disjunta pra não violar o UNIQUE no meio do caminho.
        $pdo->exec(<<<'SQL'
            CREATE TABLE vehicle_images (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                vehicle_id uuid NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
                file_id uuid NOT NULL REFERENCES files(id),
                position smallint NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE UNIQUE INDEX vehicle_images_position_unique ON vehicle_images (vehicle_id, position)');
        $pdo->exec('CREATE INDEX vehicle_images_file_id_idx ON vehicle_images (file_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE vehicle_images');
    }
};
