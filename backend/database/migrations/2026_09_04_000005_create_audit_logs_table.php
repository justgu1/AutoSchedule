<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE audit_logs (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id uuid REFERENCES users(id) ON DELETE SET NULL,
                event varchar(100) NOT NULL,
                auditable_type varchar(100) NOT NULL,
                auditable_id uuid,
                old_values jsonb,
                new_values jsonb,
                ip_address inet,
                user_agent text,
                created_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE INDEX audit_logs_user_id_idx ON audit_logs (user_id)');
        $pdo->exec('CREATE INDEX audit_logs_event_idx ON audit_logs (event)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE audit_logs');
    }
};
