<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Notifications\Ports\MailProvider;
use App\Domain\Ports\Job;

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
