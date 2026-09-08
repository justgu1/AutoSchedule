<?php

declare(strict_types=1);

namespace Tests\Domain\Audit;

use App\Domain\Audit\AuditableType;
use App\Domain\Audit\AuditEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditEventTest extends TestCase
{
    /** O `match` sem `default` lança em prefixo desconhecido, e o primeiro evento de um domínio novo seria 500. */
    #[Test]
    public function todo_evento_tem_um_tipo_auditavel_correspondente(): void
    {
        foreach (AuditEvent::cases() as $event) {
            $this->assertInstanceOf(AuditableType::class, $event->auditableType(), $event->value);
        }
    }
}
