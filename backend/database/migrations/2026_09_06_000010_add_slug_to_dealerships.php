<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

/**
 * URL pública amigável (`/concessionarias/{slug}`) não pode expor o `id`
 * (UUID) -- `slug` é gerado uma vez no domínio (`Dealership::buildSlug()`,
 * nome + 6 caracteres do id) e nunca muda depois, mesmo que o nome mude.
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE dealerships ADD COLUMN slug text');

        // Backfill pra qualquer linha pré-existente -- mesmo algoritmo do
        // `Dealership::buildSlug()`, só que em SQL. `right()`, não `substr(..., 1, 6)`:
        // UUID v7 tem timestamp nos bits iniciais, os últimos caracteres são a parte aleatória.
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
