<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Scheduler;

use App\Domain\Ports\ScheduledTask;
use App\Infrastructure\Redis\RedisConnection;
use App\Infrastructure\Scheduler\Scheduler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Teste de integração: usa o Redis real do docker-compose só pro estado de "último run". */
final class SchedulerTest extends TestCase
{
    private RedisConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
        );
    }

    protected function tearDown(): void
    {
        $this->connection->client()->del('scheduler:last_run:test-task');
    }

    #[Test]
    public function roda_a_tarefa_quando_nunca_rodou_antes(): void
    {
        $task = new SpyScheduledTask('test-task', dueIntervalSeconds: 3600);
        $scheduler = new Scheduler($this->connection, [$task]);

        $scheduler->tick(new \DateTimeImmutable());

        $this->assertSame(1, $task->runCount);
    }

    #[Test]
    public function nao_roda_de_novo_antes_do_intervalo_passar(): void
    {
        $task = new SpyScheduledTask('test-task', dueIntervalSeconds: 3600);
        $scheduler = new Scheduler($this->connection, [$task]);
        $now = new \DateTimeImmutable();

        $scheduler->tick($now);
        $scheduler->tick($now->modify('+10 seconds'));

        $this->assertSame(1, $task->runCount);
    }

    #[Test]
    public function roda_de_novo_depois_do_intervalo_passar(): void
    {
        $task = new SpyScheduledTask('test-task', dueIntervalSeconds: 60);
        $scheduler = new Scheduler($this->connection, [$task]);
        $now = new \DateTimeImmutable();

        $scheduler->tick($now);
        $scheduler->tick($now->modify('+61 seconds'));

        $this->assertSame(2, $task->runCount);
    }
}

final class SpyScheduledTask implements ScheduledTask
{
    public int $runCount = 0;

    public function __construct(private readonly string $name, private readonly int $dueIntervalSeconds)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function dueIntervalSeconds(): int
    {
        return $this->dueIntervalSeconds;
    }

    public function run(): void
    {
        $this->runCount++;
    }
}
