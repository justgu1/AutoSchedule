<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Registro público insere antes de existir autenticação, então nenhuma policy baseada em identidade serve.
        $pdo->exec(<<<'SQL'
            CREATE POLICY users_service_insert ON users
                FOR INSERT
                WITH CHECK (current_setting('app.is_service_context', true) = 'true')
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY IF EXISTS users_service_insert ON users');
    }
};
