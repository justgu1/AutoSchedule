<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use Psr\Log\LoggerInterface;

final class PostgresAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function record(AuditEvent $event, ?string $userId, array $context, string $ipAddress, ?string $userAgent): void
    {
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO audit_logs (user_id, event, auditable_type, auditable_id, new_values, ip_address, user_agent)
                VALUES (:user_id, :event, 'User', :auditable_id, :new_values, :ip_address, :user_agent)
                SQL);

            $statement->execute([
                'user_id' => $userId,
                'event' => $event->value,
                'auditable_id' => $userId,
                'new_values' => json_encode($context, JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $exception) {
            // Best-effort: falha ao auditar não pode derrubar a resposta principal.
            $this->logger->error((string) $exception);
        }
    }
}
