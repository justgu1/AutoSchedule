<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TYPE dealership_status AS ENUM ('active', 'trashed', 'deleted')");

        $pdo->exec(<<<'SQL'
            CREATE TABLE dealerships (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                owner_user_id uuid NOT NULL REFERENCES users(id),
                name varchar(160) NOT NULL,
                zip_code varchar(10) NOT NULL,
                address varchar(255) NOT NULL,
                number varchar(20) NOT NULL,
                complement varchar(120),
                neighborhood varchar(120) NOT NULL,
                city varchar(120) NOT NULL,
                state varchar(2) NOT NULL,
                latitude decimal(10,7),
                longitude decimal(10,7),
                google_place_id varchar(255),
                phone varchar(20),
                status dealership_status NOT NULL DEFAULT 'active',
                trashed_by_owner_deactivation boolean NOT NULL DEFAULT false,
                trashed_at timestamptz,
                anonymized_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE INDEX dealerships_owner_user_id_idx ON dealerships (owner_user_id)');
        $pdo->exec('CREATE INDEX dealerships_status_idx ON dealerships (status)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE dealerships');
        $pdo->exec('DROP TYPE dealership_status');
    }
};
