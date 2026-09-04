<?php

declare(strict_types=1);

namespace Tests\Domain\Support;

use App\Domain\Support\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    #[Test]
    public function gera_um_uuid_v4_valido(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            Uuid::v4(),
        );
    }

    #[Test]
    public function cada_chamada_gera_um_valor_diferente(): void
    {
        $this->assertNotSame(Uuid::v4(), Uuid::v4());
    }
}
