<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

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
        $executed = [];

        foreach ($this->seederFiles() as $name => $path) {
            /** @var Seeder $seeder */
            $seeder = require $path;
            $seeder->run($this->pdo);
            $executed[] = $name;
        }

        return $executed;
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
