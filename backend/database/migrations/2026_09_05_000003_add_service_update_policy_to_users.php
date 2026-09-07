<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Confirmar reset de senha atualiza o usuário sem Bearer, então nenhuma policy baseada em identidade serve.
        $pdo->exec(<<<'SQL'
            CREATE POLICY users_service_update ON users
                FOR UPDATE
                USING (current_setting('app.is_service_context', true) = 'true')
                WITH CHECK (current_setting('app.is_service_context', true) = 'true')
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY IF EXISTS users_service_update ON users');
    }
};
