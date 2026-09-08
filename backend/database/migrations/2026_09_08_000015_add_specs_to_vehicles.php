<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TYPE vehicle_transmission AS ENUM ('manual', 'automatic', 'automated', 'cvt')");
        $pdo->exec("CREATE TYPE vehicle_body_type AS ENUM ('hatch', 'sedan', 'suv', 'pickup', 'coupe', 'convertible', 'minivan', 'wagon')");
        $pdo->exec("CREATE TYPE vehicle_fuel_type AS ENUM ('flex', 'gasoline', 'ethanol', 'diesel', 'electric', 'hybrid')");

        $pdo->exec(<<<'SQL'
            ALTER TABLE vehicles
                ADD COLUMN manufacture_year smallint,
                ADD COLUMN model_year smallint,
                ADD COLUMN mileage_km integer,
                ADD COLUMN transmission vehicle_transmission,
                ADD COLUMN body_type vehicle_body_type,
                ADD COLUMN fuel_type vehicle_fuel_type,
                ADD COLUMN color varchar(40),
                ADD COLUMN plate_end_digit smallint,
                ADD COLUMN accepts_trade boolean NOT NULL DEFAULT false,
                ADD COLUMN ipva_paid boolean NOT NULL DEFAULT false,
                ADD COLUMN licensed boolean NOT NULL DEFAULT false
            SQL);

        // Backfill: quem já tinha `year` fica com fabricação = modelo = year -- única leitura possível do dado antigo.
        $pdo->exec('UPDATE vehicles SET manufacture_year = year, model_year = year WHERE year IS NOT NULL');

        $pdo->exec(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT vehicles_manufacture_year_plausible CHECK (manufacture_year IS NULL OR manufacture_year BETWEEN 1900 AND 2100),
                ADD CONSTRAINT vehicles_model_year_plausible CHECK (model_year IS NULL OR model_year BETWEEN 1900 AND 2100),
                ADD CONSTRAINT vehicles_model_year_not_before_manufacture CHECK (
                    manufacture_year IS NULL OR model_year IS NULL OR model_year >= manufacture_year
                ),
                ADD CONSTRAINT vehicles_mileage_non_negative CHECK (mileage_km IS NULL OR mileage_km >= 0),
                ADD CONSTRAINT vehicles_plate_end_digit_range CHECK (plate_end_digit IS NULL OR plate_end_digit BETWEEN 0 AND 9)
            SQL);

        // `search_vector` (gerada) e seu índice dependem de `year` -- refeitos contra `model_year` antes do DROP.
        $pdo->exec('DROP INDEX vehicles_search_vector_gin_idx');
        $pdo->exec('ALTER TABLE vehicles DROP COLUMN search_vector');
        $pdo->exec('ALTER TABLE vehicles DROP CONSTRAINT vehicles_year_plausible');
        $pdo->exec('ALTER TABLE vehicles DROP COLUMN year');

        $pdo->exec(<<<'SQL'
            ALTER TABLE vehicles ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple'::regconfig, coalesce(brand, '')),             'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(model, '')),              'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(version, '')),             'B') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(model_year::text, '')),    'B') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(description, '')),         'D')
            ) STORED
            SQL);
        $pdo->exec('CREATE INDEX vehicles_search_vector_gin_idx ON vehicles USING GIN (search_vector)');

        $pdo->exec('CREATE INDEX vehicles_transmission_idx ON vehicles (transmission)');
        $pdo->exec('CREATE INDEX vehicles_body_type_idx ON vehicles (body_type)');
        $pdo->exec('CREATE INDEX vehicles_fuel_type_idx ON vehicles (fuel_type)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX vehicles_search_vector_gin_idx');
        $pdo->exec('ALTER TABLE vehicles DROP COLUMN search_vector');

        $pdo->exec('ALTER TABLE vehicles ADD COLUMN year smallint');
        $pdo->exec('UPDATE vehicles SET year = model_year');
        $pdo->exec('ALTER TABLE vehicles ADD CONSTRAINT vehicles_year_plausible CHECK (year IS NULL OR year BETWEEN 1900 AND 2100)');

        $pdo->exec(<<<'SQL'
            ALTER TABLE vehicles ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple'::regconfig, coalesce(brand, '')),       'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(model, '')),       'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(version, '')),     'B') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(year::text, '')),  'B') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(description, '')), 'D')
            ) STORED
            SQL);
        $pdo->exec('CREATE INDEX vehicles_search_vector_gin_idx ON vehicles USING GIN (search_vector)');

        $pdo->exec('DROP INDEX vehicles_fuel_type_idx');
        $pdo->exec('DROP INDEX vehicles_body_type_idx');
        $pdo->exec('DROP INDEX vehicles_transmission_idx');
        $pdo->exec(<<<'SQL'
            ALTER TABLE vehicles
                DROP COLUMN manufacture_year, DROP COLUMN model_year, DROP COLUMN mileage_km,
                DROP COLUMN transmission, DROP COLUMN body_type, DROP COLUMN fuel_type,
                DROP COLUMN color, DROP COLUMN plate_end_digit,
                DROP COLUMN accepts_trade, DROP COLUMN ipva_paid, DROP COLUMN licensed
            SQL);
        $pdo->exec('DROP TYPE vehicle_fuel_type');
        $pdo->exec('DROP TYPE vehicle_body_type');
        $pdo->exec('DROP TYPE vehicle_transmission');
    }
};
