<?php

declare(strict_types=1);

namespace App\Domain\Ports;

interface ScheduledTask
{
    public function name(): string;

    public function dueIntervalSeconds(): int;

    public function run(): void;
}
