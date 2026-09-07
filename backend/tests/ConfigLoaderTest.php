<?php

declare(strict_types=1);

namespace Tests;

use App\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    #[Test]
    public function le_valor_de_app_php_na_raiz(): void
    {
        $this->assertSame('AutoSchedule', new Config()->string('name'));
    }

    #[Test]
    public function le_cada_outro_arquivo_como_grupo_com_o_nome_do_arquivo(): void
    {
        $config = new Config();

        $this->assertSame('autoschedule', $config->string('auth.jwt.issuer'));
        $this->assertSame(900, $config->int('auth.access_token_ttl'));
        $this->assertFalse($config->bool('security.cookie_secure'));
    }

    #[Test]
    public function chave_ausente_explode_em_vez_de_devolver_default(): void
    {
        $this->expectException(\RuntimeException::class);

        new Config()->string('nao.existe');
    }

    #[Test]
    public function tipo_errado_explode_no_boot(): void
    {
        $this->expectException(\RuntimeException::class);

        new Config()->int('auth.jwt.issuer');
    }

    /** Regressão: secret selado com `\n` sobrando já derrubou login do Google e autenticação do Postgres em produção. */
    #[Test]
    public function corta_espaco_em_branco_na_leitura_da_env(): void
    {
        putenv('GOOGLE_CLIENT_ID=client-id-com-newline' . "\n");

        try {
            $this->assertSame('client-id-com-newline', new Config()->string('google.client_id'));
        } finally {
            putenv('GOOGLE_CLIENT_ID');
        }
    }

    /** Espaço no meio é valor legítimo: o trim global anterior mexia em segredo que ninguém pediu pra mexer. */
    #[Test]
    public function nao_mexe_no_meio_do_valor(): void
    {
        putenv('GOOGLE_CLIENT_ID=com espaco no meio');

        try {
            $this->assertSame('com espaco no meio', new Config()->string('google.client_id'));
        } finally {
            putenv('GOOGLE_CLIENT_ID');
        }
    }
}
