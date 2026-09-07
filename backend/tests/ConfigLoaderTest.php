<?php

declare(strict_types=1);

namespace Tests;

use App\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    #[Test]
    public function config_expoe_os_valores_de_app_php(): void
    {
        $app = new Config();

        $this->assertSame('AutoSchedule', $app->config('name'));
        $this->assertIsArray($app->config('database'));
    }

    #[Test]
    public function config_expoe_cada_outro_arquivo_de_config_pelo_proprio_nome_de_arquivo(): void
    {
        $app = new Config();

        $auth = $app->config('auth');

        $this->assertIsArray($auth);
        $this->assertArrayHasKey('jwt', $auth);
    }

    #[Test]
    public function config_devolve_o_default_quando_a_chave_nao_existe(): void
    {
        $app = new Config();

        $this->assertSame('fallback', $app->config('does-not-exist', 'fallback'));
    }

    /** Regressão: secret selado com `\n` sobrando já derrubou login do Google e autenticação do Postgres em produção. */
    #[Test]
    public function config_corta_espaco_em_branco_de_valores_string_incluindo_aninhados(): void
    {
        putenv('GOOGLE_CLIENT_ID=client-id-com-newline' . "\n");

        try {
            $app = new Config();

            $google = $app->config('google');
            $this->assertIsArray($google);
            $this->assertSame('client-id-com-newline', $google['client_id']);
        } finally {
            putenv('GOOGLE_CLIENT_ID');
        }
    }
}
