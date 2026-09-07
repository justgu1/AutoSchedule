<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Sem RLS: só o hash é guardado, e ele é inútil sem o texto puro, que só existe no e-mail.
        $pdo->exec(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token_hash varchar(64) NOT NULL UNIQUE,
                expires_at timestamptz NOT NULL,
                used_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE INDEX password_reset_tokens_user_id_idx ON password_reset_tokens (user_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE password_reset_tokens');
    }
};
