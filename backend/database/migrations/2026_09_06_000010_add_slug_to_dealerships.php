<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Migration;

/** O slug existe pra a URL pública não expor o UUID, e é estável mesmo quando o nome muda. */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships ADD COLUMN slug text');

        // Mesmo algoritmo de `Dealership::buildSlug()` em SQL; `right()` porque em UUIDv7 o começo é timestamp.
        $pdo->exec(<<<'SQL'
            UPDATE dealerships
            SET slug = lower(regexp_replace(name, '[^a-zA-Z0-9]+', '-', 'g')) || '-' || right(replace(id::text, '-', ''), 6)
            WHERE slug IS NULL
            SQL);

        $pdo->exec('ALTER TABLE dealerships ALTER COLUMN slug SET NOT NULL');
        $pdo->exec('ALTER TABLE dealerships ADD CONSTRAINT dealerships_slug_unique UNIQUE (slug)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships DROP CONSTRAINT IF EXISTS dealerships_slug_unique');
        $pdo->exec('ALTER TABLE dealerships DROP COLUMN IF EXISTS slug');
    }
};
