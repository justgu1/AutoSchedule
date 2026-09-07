<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Application\Ports\QueuedJob;

/** O envelope que trafega no Redis, escrito uma vez em vez de redigitado em cada ponta. */
final readonly class QueueMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public QueuedJob $job,
        public array $payload,
        public int $attempts = 0,
    ) {
    }

    public static function fromJson(string $raw): self
    {
        /** @var array{job: string, payload: array<string, mixed>, attempts: int} $decoded */
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        return new self(QueuedJob::from($decoded['job']), $decoded['payload'], $decoded['attempts']);
    }

    public function retried(): self
    {
        return new self($this->job, $this->payload, $this->attempts + 1);
    }

    public function toJson(): string
    {
        return json_encode(
            ['job' => $this->job->value, 'payload' => $this->payload, 'attempts' => $this->attempts],
            JSON_THROW_ON_ERROR,
        );
    }
}
