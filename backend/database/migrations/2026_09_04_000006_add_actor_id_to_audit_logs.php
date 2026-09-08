<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        // user_id é a conta afetada, actor_id é quem agiu: divergem quando um admin mexe em outro usuário.
        $pdo->exec('ALTER TABLE audit_logs ADD COLUMN actor_id uuid REFERENCES users(id) ON DELETE SET NULL');
        $pdo->exec('CREATE INDEX audit_logs_actor_id_idx ON audit_logs (actor_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX IF EXISTS audit_logs_actor_id_idx');
        $pdo->exec('ALTER TABLE audit_logs DROP COLUMN actor_id');
    }
};
