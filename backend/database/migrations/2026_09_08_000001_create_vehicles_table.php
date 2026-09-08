<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TYPE vehicle_status AS ENUM ('active', 'trashed', 'deleted')");

        $pdo->exec(<<<'SQL'
            CREATE TABLE vehicles (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                dealership_id uuid NOT NULL REFERENCES dealerships(id),
                brand varchar(60) NOT NULL,
                model varchar(80) NOT NULL,
                version varchar(80),
                year smallint,
                price numeric(12,2) NOT NULL,
                description text,
                status vehicle_status NOT NULL DEFAULT 'active',
                trashed_by_dealership_trash boolean NOT NULL DEFAULT false,
                trashed_at timestamptz,
                anonymized_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT vehicles_price_non_negative CHECK (price >= 0),
                CONSTRAINT vehicles_year_plausible CHECK (year IS NULL OR year BETWEEN 1900 AND 2100)
            )
            SQL);

        $pdo->exec('CREATE INDEX vehicles_dealership_id_idx ON vehicles (dealership_id)');
        $pdo->exec('CREATE INDEX vehicles_status_idx ON vehicles (status)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE vehicles');
        $pdo->exec('DROP TYPE vehicle_status');
    }
};
