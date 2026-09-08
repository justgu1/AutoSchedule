<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Audit\AuditableType;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\Ports\AuditLogger;
use Psr\Log\LoggerInterface;

final readonly class PostgresAuditLogger implements AuditLogger
{
    public function __construct(
        private DatabaseConnection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function record(AuditEntry $entry): void
    {
        try {
            $this->connection->execute(<<<'SQL'
                INSERT INTO audit_logs (actor_id, user_id, event, auditable_type, auditable_id, new_values, ip_address, user_agent)
                VALUES (:actor_id, :user_id, :event, :auditable_type, :auditable_id, :new_values, :ip_address, :user_agent)
                SQL, [
                'actor_id' => $entry->actorId,
                // `user_id` tem FK pra `users` -- só preenche quando a entidade afetada de fato é um usuário.
                'user_id' => $entry->auditableType() === AuditableType::User ? $entry->auditableId : null,
                'event' => $entry->event->value,
                'auditable_type' => $entry->auditableType()->value,
                'auditable_id' => $entry->auditableId,
                'new_values' => json_encode($entry->context, JSON_THROW_ON_ERROR),
                'ip_address' => $entry->ipAddress,
                'user_agent' => $entry->userAgent,
            ]);
        } catch (\Throwable $exception) {
            // Best-effort: falha ao auditar não pode derrubar a resposta principal.
            $this->logger->error((string) $exception);
        }
    }
}
