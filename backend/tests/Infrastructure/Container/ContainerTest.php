<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Container;

use App\Infrastructure\Container\Container;
use App\Infrastructure\Container\ContainerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    #[Test]
    public function resolve_uma_classe_sem_construtor(): void
    {
        $container = new Container();

        $this->assertInstanceOf(EngineFixture::class, $container->get(EngineFixture::class));
    }

    #[Test]
    public function autowira_dependencia_de_construtor_recursivamente(): void
    {
        $container = new Container();

        $car = $container->get(CarFixture::class);

        $this->assertInstanceOf(CarFixture::class, $car);
        $this->assertInstanceOf(EngineFixture::class, $car->engine);
    }

    #[Test]
    public function get_devolve_a_mesma_instancia_em_chamadas_repetidas(): void
    {
        $container = new Container();

        $this->assertSame($container->get(EngineFixture::class), $container->get(EngineFixture::class));
    }

    #[Test]
    public function bind_liga_a_interface_a_implementacao_e_autowira_ela(): void
    {
        $container = new Container();
        $container->bind(FixtureInterface::class, ImplementationFixture::class);

        $needy = $container->get(NeedsInterfaceFixture::class);

        $this->assertInstanceOf(ImplementationFixture::class, $needy->dependency);
        $this->assertInstanceOf(EngineFixture::class, $needy->dependency->engine);
    }

    #[Test]
    public function bind_devolve_a_mesma_instancia_pela_interface_e_pela_concreta(): void
    {
        $container = new Container();
        $container->bind(FixtureInterface::class, ImplementationFixture::class);

        $this->assertSame($container->get(FixtureInterface::class), $container->get(ImplementationFixture::class));
    }

    #[Test]
    public function singleton_permite_binding_manual(): void
    {
        $container = new Container();
        $engine = new EngineFixture();
        $container->singleton(EngineFixture::class, fn (): EngineFixture => $engine);

        $this->assertSame($engine, $container->get(EngineFixture::class));
    }

    #[Test]
    public function lanca_excecao_com_classe_parametro_e_tipo_quando_nao_consegue_resolver(): void
    {
        $container = new Container();

        try {
            $container->get(NeedsScalarFixture::class);
            $this->fail('Expected ContainerException to be thrown.');
        } catch (ContainerException $exception) {
            $this->assertStringContainsString('$name', $exception->getMessage());
            $this->assertStringContainsString('string', $exception->getMessage());
            $this->assertStringContainsString(NeedsScalarFixture::class, $exception->getMessage());
        }
    }

    #[Test]
    public function lanca_excecao_quando_a_classe_nao_existe(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);

        // Montado em runtime de propósito: o nome não existe, é o caso sob teste.
        /** @var class-string $missing */
        $missing = implode('\\', ['App', 'Does', 'Not', 'Exist']);
        $container->get($missing);
    }

    #[Test]
    public function lanca_excecao_quando_o_tipo_e_uma_interface_sem_binding(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);

        $container->get(NeedsInterfaceFixture::class);
    }

    #[Test]
    public function detecta_dependencia_circular(): void
    {
        $container = new Container();

        try {
            $container->get(CircularAFixture::class);
            $this->fail('Expected ContainerException to be thrown.');
        } catch (ContainerException $exception) {
            $this->assertStringContainsString('CircularAFixture', $exception->getMessage());
            $this->assertStringContainsString('CircularBFixture', $exception->getMessage());
        }
    }
}

final class EngineFixture
{
}

final class CarFixture
{
    public function __construct(public EngineFixture $engine)
    {
    }
}

final class NeedsScalarFixture
{
    public function __construct(public string $name)
    {
    }
}

interface FixtureInterface
{
}

final class ImplementationFixture implements FixtureInterface
{
    public function __construct(public EngineFixture $engine)
    {
    }
}

final class NeedsInterfaceFixture
{
    public function __construct(public FixtureInterface $dependency)
    {
    }
}

final class CircularAFixture
{
    public function __construct(public CircularBFixture $b)
    {
    }
}

final class CircularBFixture
{
    public function __construct(public CircularAFixture $a)
    {
    }
}
