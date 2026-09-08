<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Application\Ports\Job;
use App\Domain\Notification\Ports\MailProvider;

final readonly class SendEmailJob implements Job
{
    public function __construct(private MailProvider $mail)
    {
    }

    public function handle(array $payload): void
    {
        [$to, $subject, $htmlBody] = [$payload['to'], $payload['subject'], $payload['html_body']];

        if (!is_string($to) || !is_string($subject) || !is_string($htmlBody)) {
            throw new \InvalidArgumentException('SendEmailJob payload must contain to/subject/html_body as strings.');
        }

        $this->mail->send($to, $subject, $htmlBody);
    }
}
