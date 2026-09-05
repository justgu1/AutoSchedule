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
    public function record(AuditEvent $event, ?string $actorId, ?string $targetUserId, array $context, string $ipAddress, ?string $userAgent): void
    {
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO audit_logs (actor_id, user_id, event, auditable_type, auditable_id, new_values, ip_address, user_agent)
                VALUES (:actor_id, :user_id, :event, 'User', :auditable_id, :new_values, :ip_address, :user_agent)
                SQL);

            $statement->execute([
                'actor_id' => $actorId,
                'user_id' => $targetUserId,
                'event' => $event->value,
                'auditable_id' => $targetUserId,
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
