<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Jobs;

use App\Application\Notification\SendEmail;
use App\Domain\Notification\Ports\MailProvider;
use App\Infrastructure\Jobs\SendEmailJob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SendEmailJobTest extends TestCase
{
    #[Test]
    public function handle_traduz_o_payload_da_fila_em_argumentos_do_caso_de_uso(): void
    {
        $mail = new SpyMailProvider();
        $job = new SendEmailJob(new SendEmail($mail));

        $job->handle(['to' => 'ada@example.com', 'subject' => 'Assunto', 'html_body' => '<p>Corpo</p>']);

        $this->assertSame('ada@example.com', $mail->to);
        $this->assertSame('Assunto', $mail->subject);
        $this->assertSame('<p>Corpo</p>', $mail->htmlBody);
    }
}

final class SpyMailProvider implements MailProvider
{
    public ?string $to = null;
    public ?string $subject = null;
    public ?string $htmlBody = null;

    public function send(string $to, string $subject, string $htmlBody): void
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
    }
}
