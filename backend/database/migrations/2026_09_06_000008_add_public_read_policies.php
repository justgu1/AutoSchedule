<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

/**
 * A policy só olha `status = 'active'`, sem checar dono, porque quem limita o alcance é a marca de
 * leitura pública na rota -- nenhuma rota de gerenciamento a seta. Ver docs/architecture.md.
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE POLICY dealerships_public_select ON dealerships
                FOR SELECT
                USING (
                    current_setting('app.is_public_read', true) = 'true'
                    AND status = 'active'
                )
            SQL);

        // RLS só libera a linha; quem decide o que do vendedor chega no front é `PublicDealershipProfile`.
        $pdo->exec(<<<'SQL'
            CREATE POLICY users_public_select ON users
                FOR SELECT
                USING (
                    current_setting('app.is_public_read', true) = 'true'
                    AND role = 'seller'
                    AND EXISTS (
                        SELECT 1 FROM dealerships
                        WHERE dealerships.owner_user_id = users.id
                        AND dealerships.status = 'active'
                    )
                )
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY IF EXISTS users_public_select ON users');
        $pdo->exec('DROP POLICY IF EXISTS dealerships_public_select ON dealerships');
    }
};
