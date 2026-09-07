<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use App\Bootstrap\ContainerFactory;
use App\Config;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Infrastructure\Http\Controllers\DealershipController;
use App\Infrastructure\Http\Controllers\OAuthController;
use App\Infrastructure\Http\Controllers\UserController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Pega erro de wiring cedo: um config nunca carregado já passou batido por todo o resto da suíte. */
#[Group('integration')]
final class ContainerFactoryTest extends TestCase
{
    #[Test]
    public function resolve_todo_controller_sem_lancar_excecao(): void
    {
        $container = ContainerFactory::build(new Config());

        // Resolver o controller arrasta todo caso de uso atrás, que é o que cobre o autowiring.
        $this->assertInstanceOf(OAuthController::class, $container->get(OAuthController::class));
        $this->assertInstanceOf(UserController::class, $container->get(UserController::class));
        $this->assertInstanceOf(DealershipController::class, $container->get(DealershipController::class));
    }

    #[Test]
    public function resolve_oauth_client_repository_sem_lancar_excecao(): void
    {
        $container = ContainerFactory::build(new Config());

        $this->assertInstanceOf(OAuthClientRepository::class, $container->get(OAuthClientRepository::class));
    }
}
