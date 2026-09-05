<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

final readonly class MigrationRunner
{
    public function __construct(
        private \PDO $pdo,
        private string $migrationsPath,
    ) {
    }

    /** @return list<string> nomes das migrations aplicadas nesta chamada, em ordem */
    public function run(): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->appliedMigrations();
        $batch = $this->nextBatch();
        $appliedNow = [];

        foreach ($this->pendingMigrationFiles($applied) as $name => $path) {
            /** @var Migration $migration */
            $migration = require $path;
            $migration->up($this->pdo);
            $this->recordMigration($name, $batch);
            $appliedNow[] = $name;
        }

        return $appliedNow;
    }

    /** @return list<string> nomes revertidos, em ordem de reversão */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->latestBatch();

        if ($batch === null) {
            return [];
        }

        $rolledBack = [];

        foreach ($this->migrationsInBatch($batch) as $name) {
            /** @var Migration $migration */
            $migration = require $this->migrationsPath . '/' . $name . '.php';
            $migration->down($this->pdo);
            $this->deleteMigrationRecord($name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id serial PRIMARY KEY,
                migration varchar(255) NOT NULL UNIQUE,
                batch integer NOT NULL,
                applied_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);
    }

    /** @return list<string> */
    private function appliedMigrations(): array
    {
        $statement = $this->pdo->query('SELECT migration FROM migrations');

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param list<string> $applied
     * @return array<string, string> nome da migration => caminho do arquivo, em ordem
     */
    private function pendingMigrationFiles(array $applied): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);

        $pending = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (!in_array($name, $applied, true)) {
                $pending[$name] = $file;
            }
        }

        return $pending;
    }

    private function recordMigration(string $name, int $batch): void
    {
        $statement = $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $statement->execute([$name, $batch]);
    }

    private function nextBatch(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
    }

    private function latestBatch(): ?int
    {
        $batch = $this->pdo->query('SELECT MAX(batch) FROM migrations')->fetchColumn();

        return $batch === null ? null : (int) $batch;
    }

    /** @return list<string> nomes do batch, em ordem decrescente (reverso da aplicação) */
    private function migrationsInBatch(int $batch): array
    {
        $statement = $this->pdo->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC');
        $statement->execute([$batch]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function deleteMigrationRecord(string $name): void
    {
        $statement = $this->pdo->prepare('DELETE FROM migrations WHERE migration = ?');
        $statement->execute([$name]);
    }
}
