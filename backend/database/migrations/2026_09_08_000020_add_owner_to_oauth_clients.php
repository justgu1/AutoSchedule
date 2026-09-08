<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE oauth_clients ADD COLUMN owner_user_id uuid REFERENCES users(id) ON DELETE CASCADE');
        // Soft: revogar não pode liberar o client_id pra reuso, e o histórico continua auditável.
        $pdo->exec('ALTER TABLE oauth_clients ADD COLUMN revoked_at timestamptz');
        $pdo->exec('CREATE INDEX oauth_clients_owner_user_id_idx ON oauth_clients (owner_user_id) WHERE owner_user_id IS NOT NULL');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX oauth_clients_owner_user_id_idx');
        $pdo->exec('ALTER TABLE oauth_clients DROP COLUMN revoked_at');
        $pdo->exec('ALTER TABLE oauth_clients DROP COLUMN owner_user_id');
    }
};
