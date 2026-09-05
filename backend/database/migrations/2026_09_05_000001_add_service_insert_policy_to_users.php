<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Registro público (POST /api/register) insere um customer/seller
        // ANTES de existir qualquer autenticação -- users_admin_insert exige
        // current_user_role='admin', que não existe nesse fluxo. Mesma lógica
        // de users_service_select: só vale pra rota marcada como serviceContext.
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
