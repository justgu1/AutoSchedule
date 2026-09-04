<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Domain\Auth\OAuthService;
use App\Domain\Auth\Ports\OAuthClientRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: resolver os bindings de banco/JWT toca recurso real
 * (conexão Postgres, arquivo de chave) -- precisa rodar dentro do compose.
 * Existe pra pegar erro de wiring cedo (ex: config/auth.php nunca carregado,
 * já aconteceu uma vez).
 */
final class ContainerFactoryTest extends TestCase
{
    #[Test]
    public function resolve_oauth_service_sem_lancar_excecao(): void
    {
        $container = ContainerFactory::build(new Application());

        $this->assertInstanceOf(OAuthService::class, $container->get(OAuthService::class));
    }

    #[Test]
    public function resolve_oauth_client_repository_sem_lancar_excecao(): void
    {
        $container = ContainerFactory::build(new Application());

        $this->assertInstanceOf(OAuthClientRepository::class, $container->get(OAuthClientRepository::class));
    }
}
