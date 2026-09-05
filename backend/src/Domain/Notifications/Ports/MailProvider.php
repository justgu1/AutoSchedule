<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Ports;

interface MailProvider
{
    public function send(string $to, string $subject, string $htmlBody): void;
}
