<?php

declare(strict_types=1);

namespace App\Domain\Audit\Ports;

use App\Domain\Audit\AuditEntry;

interface AuditLogger
{
    public function record(AuditEntry $entry): void;
}
