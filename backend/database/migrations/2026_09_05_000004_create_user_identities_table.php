<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class implements Migration {
    public function up(\PDO $pdo): void
    {
        // Sem RLS -- mesmo padrão de oauth_refresh_tokens/password_reset_tokens:
        // só a aplicação toca essa tabela (linka/consulta identidade social
        // durante o login), nunca é exposta direto por rota nenhuma.
        $pdo->exec(<<<'SQL'
            CREATE TABLE user_identities (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                provider varchar(20) NOT NULL,
                provider_user_id varchar(255) NOT NULL,
                email varchar(255) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                UNIQUE (provider, provider_user_id)
            )
            SQL);

        $pdo->exec('CREATE INDEX user_identities_user_id_idx ON user_identities (user_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE user_identities');
    }
};
