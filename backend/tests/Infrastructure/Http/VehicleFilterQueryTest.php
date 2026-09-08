<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\VehicleFilterQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VehicleFilterQueryTest extends TestCase
{
    #[Test]
    public function query_string_vazia_vira_filtro_sem_nada(): void
    {
        $filters = VehicleFilterQuery::fromRequest($this->requestWith([]));

        $this->assertNull($filters->term);
        $this->assertNull($filters->yearMin);
        $this->assertNull($filters->priceMax);
    }

    #[Test]
    public function le_os_filtros_empilhados_da_query_string(): void
    {
        $filters = VehicleFilterQuery::fromRequest($this->requestWith([
            'q' => 'onix',
            'brand' => 'Chevrolet',
            'model' => 'Onix',
            'year_min' => '2018',
            'year_max' => '2024',
            'price_min' => '50000',
            'price_max' => '90000.50',
        ]));

        $this->assertSame('onix', $filters->term);
        $this->assertSame('Chevrolet', $filters->brand);
        $this->assertSame(2018, $filters->yearMin);
        $this->assertSame(2024, $filters->yearMax);
        $this->assertSame(5000000, $filters->priceMin?->cents);
        $this->assertSame(9000050, $filters->priceMax?->cents);
    }

    #[Test]
    public function campo_em_branco_conta_como_ausente(): void
    {
        $filters = VehicleFilterQuery::fromRequest($this->requestWith(['q' => '   ', 'brand' => '']));

        $this->assertNull($filters->term);
        $this->assertNull($filters->brand);
    }

    /** Query string é editável na barra de endereço: lixo ali vira filtro ausente, não 500. */
    #[Test]
    public function valor_nao_numerico_ou_ano_implausivel_e_ignorado(): void
    {
        $filters = VehicleFilterQuery::fromRequest($this->requestWith([
            'year_min' => 'ontem',
            'year_max' => '1800',
            'price_min' => 'barato',
        ]));

        $this->assertNull($filters->yearMin);
        $this->assertNull($filters->yearMax);
        $this->assertNull($filters->priceMin);
    }

    /** @param array<string, string> $query */
    private function requestWith(array $query): Request
    {
        return new Request(method: 'GET', path: '/api/vehicles', query: $query);
    }
}
