<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Pagination;

use App\Infrastructure\Pagination\PaginationPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginationPolicyTest extends TestCase
{
    #[Test]
    public function resolve_usa_os_padroes_quando_nada_e_informado(): void
    {
        $policy = new PaginationPolicy(defaultPerPage: 20, maxPerPage: 100);

        $this->assertSame([1, 20], $policy->resolve(null, null));
    }

    #[Test]
    public function resolve_respeita_page_e_per_page_informados(): void
    {
        $policy = new PaginationPolicy(defaultPerPage: 20, maxPerPage: 100);

        $this->assertSame([3, 10], $policy->resolve('3', '10'));
    }

    #[Test]
    public function resolve_nunca_deixa_per_page_passar_do_maximo(): void
    {
        $policy = new PaginationPolicy(defaultPerPage: 20, maxPerPage: 100);

        $this->assertSame([1, 100], $policy->resolve(null, '500'));
    }

    #[Test]
    public function resolve_nunca_deixa_page_ou_per_page_irem_abaixo_de_1(): void
    {
        $policy = new PaginationPolicy(defaultPerPage: 20, maxPerPage: 100);

        $this->assertSame([1, 1], $policy->resolve('0', '-5'));
    }
}
