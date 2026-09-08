<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

interface ScheduledTask
{
    public function name(): string;

    public function dueIntervalSeconds(): int;

    public function run(): void;
}
