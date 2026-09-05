<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE oauth_clients (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                client_id varchar(80) NOT NULL UNIQUE,
                name varchar(120) NOT NULL,
                type varchar(20) NOT NULL CHECK (type IN ('public', 'confidential')),
                secret_hash varchar(255),
                allowed_grant_types text[] NOT NULL,
                redirect_uris text[],
                allowed_scopes text[] NOT NULL DEFAULT '{}',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT oauth_clients_secret_required_for_confidential
                    CHECK (type = 'public' OR secret_hash IS NOT NULL)
            )
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE oauth_clients');
    }
};
