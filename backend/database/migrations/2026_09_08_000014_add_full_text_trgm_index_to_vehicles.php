<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Separado de `vehicles_name_trgm_idx` (esse serve só ao ranking de marca/modelo/versão) --
        // este serve ao ILIKE de substring, que também precisa alcançar `description`.
        $pdo->exec(<<<'SQL'
            CREATE INDEX vehicles_full_text_trgm_idx ON vehicles
            USING GIN ((brand || ' ' || model || ' ' || coalesce(version, '') || ' ' || coalesce(description, '')) gin_trgm_ops)
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX vehicles_full_text_trgm_idx');
    }
};
