<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\PostgresArray;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PostgresArrayTest extends TestCase
{
    #[Test]
    public function to_text_monta_o_literal_de_array_do_postgres(): void
    {
        $this->assertSame('{a,b,c}', PostgresArray::toText(['a', 'b', 'c']));
    }

    #[Test]
    public function to_text_de_lista_vazia_e_chaves_vazias(): void
    {
        $this->assertSame('{}', PostgresArray::toText([]));
    }

    #[Test]
    public function from_text_faz_o_parse_do_literal_de_volta_pra_lista(): void
    {
        $this->assertSame(['a', 'b', 'c'], PostgresArray::fromText('{a,b,c}'));
    }

    #[Test]
    public function from_text_de_chaves_vazias_ou_null_e_lista_vazia(): void
    {
        $this->assertSame([], PostgresArray::fromText('{}'));
        $this->assertSame([], PostgresArray::fromText(null));
    }
}
