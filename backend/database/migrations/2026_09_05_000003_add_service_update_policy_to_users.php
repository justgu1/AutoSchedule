<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class implements Migration {
    public function up(\PDO $pdo): void
    {
        // Confirmação de reset de senha (PUT /me/password com reset_token) faz
        // UPDATE em users SEM Bearer -- users_self_or_admin_update exige
        // current_user_id/role, que não existem nesse fluxo. Mesma lógica de
        // users_service_insert: só vale pra rota marcada como serviceContext.
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
