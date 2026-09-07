<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Schema;

final readonly class SeederRunner
{
    public function __construct(
        private \PDO $pdo,
        private string $seedersPath,
    ) {
    }

    /** @return list<string> nomes dos seeders executados, em ordem */
    public function run(): array
    {
        // Sem elevar o papel aqui, a RLS barra o seed ao criar o admin. `SET LOCAL` mantém isso preso à transação.
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->pdo->exec("SET LOCAL app.current_user_role = 'admin'");

            $executed = [];

            foreach ($this->seederFiles() as $name => $path) {
                /** @var Seeder $seeder */
                $seeder = require $path;
                $seeder->run($this->pdo);
                $executed[] = $name;
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $executed;
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /** @return array<string, string> nome => caminho, em ordem */
    private function seederFiles(): array
    {
        $files = glob($this->seedersPath . '/*.php') ?: [];
        sort($files);

        $seeders = [];

        foreach ($files as $file) {
            $seeders[basename($file, '.php')] = $file;
        }

        return $seeders;
    }
}
