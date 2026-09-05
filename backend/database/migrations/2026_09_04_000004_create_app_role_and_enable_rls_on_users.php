<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Senha vem de env porque a interface Migration só recebe PDO -- não
        // tem outro jeito de injetar config aqui sem mudar a assinatura.
        $password = getenv('DB_APP_PASSWORD') ?: 'changeme';
        $roleExists = (bool) $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = 'autoschedule_app'")->fetchColumn();

        if (!$roleExists) {
            $pdo->exec('CREATE ROLE autoschedule_app LOGIN PASSWORD ' . $pdo->quote($password) . ' NOSUPERUSER NOBYPASSRLS');
        }

        $pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO autoschedule_app');
        $pdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO autoschedule_app');

        $pdo->exec('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE users FORCE ROW LEVEL SECURITY');

        // admin vê/edita tudo; qualquer outro role só a própria linha.
        $pdo->exec(<<<'SQL'
            CREATE POLICY users_self_or_admin_select ON users
                FOR SELECT
                USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                )
            SQL);

        // Login/refresh (POST /api/oauth/token) buscam usuário por email/id
        // ANTES de existir qualquer autenticação -- não tem "current_user_id"
        // pra comparar ainda. Sem essa policy, o próprio login ficaria
        // bloqueado pelo RLS (ninguém nunca teria contexto pra logar).
        // AuthContextMiddleware seta esse contexto só pra rota marcada como
        // serviceContext, nunca em qualquer request sem autenticação.
        $pdo->exec(<<<'SQL'
            CREATE POLICY users_service_select ON users
                FOR SELECT
                USING (current_setting('app.is_service_context', true) = 'true')
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE POLICY users_self_or_admin_update ON users
                FOR UPDATE
                USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                )
                WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                )
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE POLICY users_admin_insert ON users
                FOR INSERT
                WITH CHECK (current_setting('app.current_user_role', true) = 'admin')
            SQL);

        $pdo->exec(<<<'SQL'
            CREATE POLICY users_admin_delete ON users
                FOR DELETE
                USING (current_setting('app.current_user_role', true) = 'admin')
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP POLICY IF EXISTS users_admin_delete ON users');
        $pdo->exec('DROP POLICY IF EXISTS users_admin_insert ON users');
        $pdo->exec('DROP POLICY IF EXISTS users_service_select ON users');
        $pdo->exec('DROP POLICY IF EXISTS users_self_or_admin_update ON users');
        $pdo->exec('DROP POLICY IF EXISTS users_self_or_admin_select ON users');
        $pdo->exec('ALTER TABLE users NO FORCE ROW LEVEL SECURITY');
        $pdo->exec('ALTER TABLE users DISABLE ROW LEVEL SECURITY');
        $pdo->exec('REVOKE ALL ON ALL TABLES IN SCHEMA public FROM autoschedule_app');
    }
};
