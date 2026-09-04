<?php

declare(strict_types=1);

namespace Tests;

use App\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    #[Test]
    public function config_expoe_os_valores_de_app_php(): void
    {
        $app = new Application();

        $this->assertSame('AutoSchedule', $app->config('name'));
        $this->assertIsArray($app->config('database'));
    }

    #[Test]
    public function config_expoe_cada_outro_arquivo_de_config_pelo_proprio_nome_de_arquivo(): void
    {
        $app = new Application();

        $auth = $app->config('auth');

        $this->assertIsArray($auth);
        $this->assertArrayHasKey('jwt', $auth);
    }

    #[Test]
    public function config_devolve_o_default_quando_a_chave_nao_existe(): void
    {
        $app = new Application();

        $this->assertSame('fallback', $app->config('does-not-exist', 'fallback'));
    }
}
