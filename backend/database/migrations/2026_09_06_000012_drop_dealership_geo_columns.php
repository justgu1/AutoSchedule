<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

/** Nasceram para um geocoder que nunca foi escrito: nenhuma linha jamais teve valor nas três. */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships DROP COLUMN IF EXISTS latitude, DROP COLUMN IF EXISTS longitude, DROP COLUMN IF EXISTS google_place_id');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships ADD COLUMN latitude decimal(10,7), ADD COLUMN longitude decimal(10,7), ADD COLUMN google_place_id varchar(255)');
    }
};
