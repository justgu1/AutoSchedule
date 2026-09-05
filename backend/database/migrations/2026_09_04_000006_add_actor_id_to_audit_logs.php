<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class implements Migration {
    public function up(\PDO $pdo): void
    {
        // user_id é a conta AFETADA pela ação (o "no quê"); actor_id é quem
        // executou a ação (o "quem"). Pra ação sobre a própria conta os dois
        // são o mesmo id; num admin mexendo em outro usuário, divergem -- sem
        // essa coluna não dava pra saber quem de fato agiu.
        $pdo->exec('ALTER TABLE audit_logs ADD COLUMN actor_id uuid REFERENCES users(id) ON DELETE SET NULL');
        $pdo->exec('CREATE INDEX audit_logs_actor_id_idx ON audit_logs (actor_id)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX IF EXISTS audit_logs_actor_id_idx');
        $pdo->exec('ALTER TABLE audit_logs DROP COLUMN actor_id');
    }
};
