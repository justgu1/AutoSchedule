<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            UPDATE oauth_clients
            SET allowed_grant_types = array_append(allowed_grant_types, 'google')
            WHERE client_id = 'autoschedule-web' AND NOT ('google' = ANY(allowed_grant_types))
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            UPDATE oauth_clients
            SET allowed_grant_types = array_remove(allowed_grant_types, 'google')
            WHERE client_id = 'autoschedule-web'
            SQL);
    }
};
