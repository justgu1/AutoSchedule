<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // Sem RLS -- mesmo padrão de user_identities/password_reset_tokens:
        // só a aplicação toca essa tabela, nunca é exposta direto por rota.
        $pdo->exec(<<<'SQL'
            CREATE TABLE files (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                path varchar(500) NOT NULL UNIQUE,
                original_name varchar(255) NOT NULL,
                mime_type varchar(100) NOT NULL,
                size_bytes bigint NOT NULL,
                checksum varchar(64) NOT NULL,
                uploaded_by uuid REFERENCES users(id) ON DELETE SET NULL,
                created_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE INDEX files_uploaded_by_idx ON files (uploaded_by)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE files');
    }
};
