<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Domain\Notification\Ports\MailProvider;

final readonly class SendEmail
{
    public function __construct(private MailProvider $mail)
    {
    }

    public function __invoke(string $to, string $subject, string $htmlBody): void
    {
        $this->mail->send($to, $subject, $htmlBody);
    }
}
