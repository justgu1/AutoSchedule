<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Notifications\Ports\MailProvider;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

final readonly class SymfonyMailProvider implements MailProvider
{
    private Mailer $mailer;

    /** @param string $dsn DSN completo do Symfony Mailer -- `smtp://host:port` (Mailpit, sem auth) ou `smtps://user:pass@host:port` (SMTP real, TLS implícito) */
    public function __construct(string $dsn, private string $from)
    {
        $this->mailer = new Mailer(Transport::fromDsn($dsn));
    }

    public function send(string $to, string $subject, string $htmlBody): void
    {
        $email = new Email()
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);

        $this->mailer->send($email);
    }
}
