<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

/**
 * Página pública da concessionária (`GET /dealerships/{id}`, resposta muda
 * conforme quem pergunta) precisa enxergar a concessionária ativa e o nome
 * do vendedor dono dela quando quem chama não é dono/admin -- sem policy
 * nova, RLS bloquearia as duas linhas pra qualquer sessão sem
 * `current_user_id`/role de dono/admin e sem `is_service_context`.
 *
 * `app.is_public_read` só é setado pelo AuthContextMiddleware nas rotas
 * marcadas `publicRead: true`, e é COMPOSTO com o contexto autenticado
 * normal (não alternativo) -- um seller comum autenticado batendo em
 * `GET /dealerships/{id}` de outro seller também recebe a flag, senão só
 * cairia no fallback público quem não manda Bearer nenhum. Nenhuma rota de
 * gerenciamento (`PATCH`/`DELETE`/`POST .../restore`/etc) seta essa flag --
 * é por isso que ela pode ficar só no `status = 'active'`, sem checar quem é
 * o dono: só a rota certa (`show()`) já limita onde essa visibilidade extra
 * se aplica.
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

        // Só o seller dono de alguma concessionária ativa fica visível -- e só
        // o nome chega no front (DealershipController::show() decide o que
        // expor, RLS só libera a linha em si).
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
