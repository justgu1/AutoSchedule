<?php

declare(strict_types=1);

namespace Tests\Domain\Support;

use App\Domain\Support\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    #[Test]
    public function gera_um_uuid_v7_valido(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            Uuid::v7(),
        );
    }

    #[Test]
    public function cada_chamada_gera_um_valor_diferente(): void
    {
        $this->assertNotSame(Uuid::v7(), Uuid::v7());
    }

    #[Test]
    public function e_ordenavel_no_tempo_uuids_gerados_em_sequencia_nao_diminuem(): void
    {
        $first = Uuid::v7();
        usleep(2_000); // garante um milissegundo de diferença no timestamp embutido
        $second = Uuid::v7();

        $this->assertLessThanOrEqual(0, strcmp($first, $second));
    }
}
