<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

/** CEP não troca de endereço, então nem TTL nem RLS fazem sentido: é referência pública. */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE zip_code_cache (
                zip_code text PRIMARY KEY,
                street text NOT NULL,
                neighborhood text NOT NULL,
                city text NOT NULL,
                state text NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS zip_code_cache');
    }
};
