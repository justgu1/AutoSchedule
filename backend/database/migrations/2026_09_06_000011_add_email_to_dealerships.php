<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

/** E-mail próprio da concessionária (contato do negócio) -- mesma nulidade opcional do `phone` que já existia. */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships ADD COLUMN email text');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships DROP COLUMN IF EXISTS email');
    }
};
