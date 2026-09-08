<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE vehicle_availability_rules (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                vehicle_id uuid NOT NULL REFERENCES vehicles(id),
                weekday smallint NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT vehicle_rules_weekday_range CHECK (weekday BETWEEN 0 AND 6),
                CONSTRAINT vehicle_rules_valid_interval CHECK (start_time < end_time)
            )
            SQL);

        $pdo->exec('CREATE INDEX vehicle_availability_rules_vehicle_id_idx ON vehicle_availability_rules (vehicle_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE vehicle_availability_rules');
    }
};
