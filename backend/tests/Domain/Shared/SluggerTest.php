<?php

declare(strict_types=1);

namespace Tests\Domain\Shared;

use App\Domain\Shared\Slugger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SluggerTest extends TestCase
{
    #[Test]
    public function normaliza_espacos_e_pontuacao_pra_hifen(): void
    {
        $this->assertSame('auto-center-prime', Slugger::slugify('Auto Center Prime!'));
    }

    /** Regressão: `iconv('UTF-8','ASCII//TRANSLIT',...)` vira "~a" pra "ã", nunca "a" -- quebrava a palavra em dois. */
    #[Test]
    public function remove_acento_sem_quebrar_a_palavra(): void
    {
        $this->assertSame('sao-paulo-concessionaria', Slugger::slugify('São Paulo Concessionária'));
        $this->assertSame('minas-veiculos-savassi', Slugger::slugify('Minas Veículos Savassi'));
        $this->assertSame('brasilia-veiculos-asa-sul', Slugger::slugify('Brasília Veículos Asa Sul'));
    }

    #[Test]
    public function nunca_comeca_ou_termina_com_hifen(): void
    {
        $this->assertSame('so-simbolos', Slugger::slugify('  --- So Símbolos !!! ---  '));
    }
}
