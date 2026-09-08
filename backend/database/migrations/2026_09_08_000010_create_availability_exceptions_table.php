<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE availability_exceptions (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                dealership_id uuid REFERENCES dealerships(id),
                vehicle_id uuid REFERENCES vehicles(id),
                date date NOT NULL,
                start_time time,
                end_time time,
                is_available boolean NOT NULL,
                reason varchar(255),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT availability_exceptions_exactly_one_scope CHECK ((dealership_id IS NOT NULL) <> (vehicle_id IS NOT NULL)),
                CONSTRAINT availability_exceptions_valid_interval CHECK (start_time IS NULL OR end_time IS NULL OR start_time < end_time),
                CONSTRAINT availability_exceptions_open_needs_interval CHECK (is_available = false OR (start_time IS NOT NULL AND end_time IS NOT NULL))
            )
            SQL);

        $pdo->exec('CREATE INDEX availability_exceptions_dealership_date_idx ON availability_exceptions (dealership_id, date) WHERE dealership_id IS NOT NULL');
        $pdo->exec('CREATE INDEX availability_exceptions_vehicle_date_idx ON availability_exceptions (vehicle_id, date) WHERE vehicle_id IS NOT NULL');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE availability_exceptions');
    }
};
