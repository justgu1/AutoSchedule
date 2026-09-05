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

    public function __construct(string $host, int $port, private string $from)
    {
        // Sem auth/TLS -- Mailpit (dev) não exige nenhum dos dois. Um SMTP
        // real de produção troca isso só via env, sem mudar código.
        $this->mailer = new Mailer(Transport::fromDsn("smtp://{$host}:{$port}"));
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
