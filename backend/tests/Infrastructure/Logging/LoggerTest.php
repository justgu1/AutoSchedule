<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Logging;

use App\Infrastructure\Logging\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    #[Test]
    public function escreve_uma_linha_json_estruturada_com_level_message_e_context(): void
    {
        $stream = fopen('php://memory', 'r+');
        $logger = new Logger($stream);

        $logger->info('user logged in', ['user_id' => '123']);

        $line = $this->readLine($stream);

        $this->assertSame('info', $line['level']);
        $this->assertSame('user logged in', $line['message']);
        $this->assertSame(['user_id' => '123'], $line['context']);
        $this->assertArrayHasKey('timestamp', $line);
    }

    #[Test]
    public function context_e_vazio_por_padrao(): void
    {
        $stream = fopen('php://memory', 'r+');
        $logger = new Logger($stream);

        $logger->error('boom');

        $this->assertSame([], $this->readLine($stream)['context']);
    }

    #[Test]
    public function os_metodos_psr3_encaminham_o_level_correto(): void
    {
        $stream = fopen('php://memory', 'r+');
        $logger = new Logger($stream);

        $logger->warning('careful');

        $this->assertSame('warning', $this->readLine($stream)['level']);
    }

    /** @return array<string, mixed> */
    private function readLine($stream): array
    {
        rewind($stream);
        $decoded = json_decode((string) fgets($stream), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
