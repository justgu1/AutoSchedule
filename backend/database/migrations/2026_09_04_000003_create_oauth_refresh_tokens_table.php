<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE oauth_refresh_tokens (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                token_hash varchar(255) NOT NULL UNIQUE,
                family_id uuid NOT NULL,
                client_id uuid NOT NULL REFERENCES oauth_clients(id),
                user_id uuid REFERENCES users(id),
                scopes text[] NOT NULL DEFAULT '{}',
                expires_at timestamptz NOT NULL,
                revoked_at timestamptz,
                replaced_by_id uuid REFERENCES oauth_refresh_tokens(id),
                created_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE INDEX oauth_refresh_tokens_family_id_idx ON oauth_refresh_tokens (family_id)');
        $pdo->exec('CREATE INDEX oauth_refresh_tokens_user_id_idx ON oauth_refresh_tokens (user_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE oauth_refresh_tokens');
    }
};
