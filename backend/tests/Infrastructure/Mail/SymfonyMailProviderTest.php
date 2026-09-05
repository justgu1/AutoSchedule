<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Mail;

use App\Infrastructure\Mail\SymfonyMailProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: manda e-mail de verdade pro Mailpit real (mesmo
 * padrão de verificação já usado pro fluxo de reset de senha) -- confirma que
 * o DSN sem auth (dev) ainda funciona depois da mudança de host/porta soltos
 * pra DSN completo. Host vem de env (`MAIL_HOST`) -- via docker-compose é
 * `mailpit`, via CI (service container do GitHub Actions) é `127.0.0.1`.
 */
final class SymfonyMailProviderTest extends TestCase
{
    #[Test]
    public function envia_um_email_real_via_mailpit_com_dsn_sem_auth(): void
    {
        $host = getenv('MAIL_HOST') ?: '127.0.0.1';
        $provider = new SymfonyMailProvider("smtp://{$host}:1025", 'test@autoschedule.local');
        $subject = 'SymfonyMailProviderTest ' . bin2hex(random_bytes(8));

        $provider->send('destinatario@example.com', $subject, '<p>corpo do teste</p>');

        $this->assertTrue($this->arrivedInMailpit($subject), 'E-mail não chegou no Mailpit dentro do timeout.');
    }

    #[Test]
    public function aceita_dsn_smtps_com_auth_sem_lancar_excecao_na_construcao(): void
    {
        // smtps:// (TLS implícito, porta 465) é o esquema usado por SMTP real
        // em produção -- Transport::fromDsn só parseia aqui, não conecta
        // (conexão de verdade só acontece em send()), então dá pra confirmar
        // que o esquema é aceito sem precisar de um servidor SMTP real.
        $provider = new SymfonyMailProvider('smtps://user:pass@smtp.example.invalid:465', 'test@autoschedule.local');

        $this->assertInstanceOf(SymfonyMailProvider::class, $provider);
    }

    private function arrivedInMailpit(string $subject): bool
    {
        $host = getenv('MAIL_HOST') ?: '127.0.0.1';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $raw = file_get_contents("http://{$host}:8025/api/v1/messages?limit=20");
            /** @var array{messages?: list<array{Subject: string}>} $list */
            $list = json_decode($raw !== false ? $raw : '{}', true, flags: JSON_THROW_ON_ERROR);

            foreach ($list['messages'] ?? [] as $message) {
                if ($message['Subject'] === $subject) {
                    return true;
                }
            }

            usleep(250_000);
        }

        return false;
    }
}
