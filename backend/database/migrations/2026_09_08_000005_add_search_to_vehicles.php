<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Gerada, não trigger nem escrita pela aplicação: caminho novo que esqueça de recalcular
        // produziria veículo que some da busca em silêncio. `::regconfig` é o que a torna IMMUTABLE.
        $pdo->exec(<<<'SQL'
            ALTER TABLE vehicles ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple'::regconfig, coalesce(brand, '')),       'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(model, '')),       'A') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(version, '')),     'B') ||
                setweight(to_tsvector('simple'::regconfig, coalesce(year::text, '')),  'B') ||
                -- Peso D alcança quem cita a marca só no texto livre, sem passar na frente do campo próprio.
                setweight(to_tsvector('simple'::regconfig, coalesce(description, '')), 'D')
            ) STORED
            SQL);

        // `simple` e não `portuguese`: stopword come versão curta e stemming quebra nome próprio, sem comprar recall.
        $pdo->exec('CREATE INDEX vehicles_search_vector_gin_idx ON vehicles USING GIN (search_vector)');

        // Um índice de expressão, não três por coluna: o operador `%` casa contra a concatenação inteira.
        $pdo->exec(<<<'SQL'
            CREATE INDEX vehicles_name_trgm_idx ON vehicles
            USING GIN ((brand || ' ' || model || ' ' || coalesce(version, '')) gin_trgm_ops)
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX vehicles_name_trgm_idx');
        $pdo->exec('DROP INDEX vehicles_search_vector_gin_idx');
        $pdo->exec('ALTER TABLE vehicles DROP COLUMN search_vector');
        // A extensão fica: é do banco, não desta tabela, e outra coisa pode ter passado a depender dela.
    }
};
