<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Persistence\DatabaseConnection;
use App\Infrastructure\Persistence\PostgresConnection;

final readonly class CliKernel
{
    private function __construct(
        public Config $config,
        public Container $container,
    ) {
    }

    public static function boot(): self
    {
        $config = new Config();

        return new self($config, ContainerFactory::build($config));
    }

    /**
     * Sem request HTTP nada seta `current_user_id`, então o RLS esconderia toda linha de um processo
     * em background. `SET` e não `SET LOCAL` porque a conexão vive o processo inteiro, sem transação por job.
     */
    public function enterServiceContext(): void
    {
        $this->retryWhileDatabaseWarmsUp(
            fn (): bool => (bool) $this->container->get(DatabaseConnection::class)->pdo()->exec("SET app.is_service_context = 'true'"),
        );
    }

    /** A role da aplicação nasce numa migration, então worker e scheduler podem subir antes de ela existir. */
    private function retryWhileDatabaseWarmsUp(\Closure $connect): void
    {
        $maxAttempts = 30;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $connect();

                return;
            } catch (\PDOException $exception) {
                if ($attempt === $maxAttempts) {
                    throw $exception;
                }

                echo sprintf("Aguardando banco de dados (tentativa %d/%d): %s\n", $attempt, $maxAttempts, $exception->getMessage());
                sleep(1);
            }
        }
    }

    /** Migration e seed rodam como a role de manutenção, não a restrita que o runtime usa. */
    public function maintenanceConnection(?string $database = null): PostgresConnection
    {
        return new PostgresConnection(
            driver: $this->config->string('database.driver'),
            host: $this->config->string('database.host'),
            port: $this->config->int('database.port'),
            database: $database ?? $this->config->string('database.database'),
            username: $this->config->string('database.username'),
            password: $this->config->string('database.password'),
        );
    }
}
