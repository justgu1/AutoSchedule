<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE oauth_clients ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE oauth_clients FORCE ROW LEVEL SECURITY');

        $ownerOrAdmin = <<<'SQL'
            current_setting('app.current_user_role', true) = 'admin'
            OR owner_user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
            SQL;

        // `is_service_context` é `!authenticated` -- some se um cookie velho ainda grudar na requisição.
        // `owner_user_id IS NULL` cobre isso à parte: client de sistema nunca teve RLS, a linha não é segredo.
        $pdo->exec(<<<SQL
            CREATE POLICY oauth_clients_select ON oauth_clients FOR SELECT USING (
                owner_user_id IS NULL
                OR current_setting('app.is_service_context', true) = 'true'
                OR ({$ownerOrAdmin})
            )
            SQL);
        $pdo->exec(<<<SQL
            CREATE POLICY oauth_clients_insert ON oauth_clients FOR INSERT WITH CHECK ({$ownerOrAdmin})
            SQL);
        $pdo->exec(<<<SQL
            CREATE POLICY oauth_clients_update ON oauth_clients FOR UPDATE USING ({$ownerOrAdmin}) WITH CHECK ({$ownerOrAdmin})
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY oauth_clients_update ON oauth_clients');
        $pdo->exec('DROP POLICY oauth_clients_insert ON oauth_clients');
        $pdo->exec('DROP POLICY oauth_clients_select ON oauth_clients');
        $pdo->exec('ALTER TABLE oauth_clients NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE oauth_clients DISABLE ROW LEVEL SECURITY');
    }
};
