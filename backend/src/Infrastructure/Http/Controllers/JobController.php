<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Ports\JobProgress;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\StreamedResponse;

/**
 * Não sabe o que o job faz, só repassa o que ele reportou -- serve qualquer job assíncrono.
 * Autorização é só "logado": o id é UUID não adivinhável e o progresso não carrega dado sensível.
 */
final readonly class JobController
{
    private const int MAX_STREAM_SECONDS = 55;
    private const int POLL_INTERVAL_MICROSECONDS = 400_000;

    public function __construct(private JobProgress $jobStatus)
    {
    }

    public function show(Request $request): Response
    {
        return Response::success($this->requireStatus($request));
    }

    /** Cair no meio não perde nada: o estado vive fora da conexão, e o `EventSource` reconecta sozinho. */
    public function events(Request $request): Response
    {
        // Job inexistente vira 404 JSON normal, antes de a resposta se comprometer com event-stream.
        $this->requireStatus($request);
        $jobId = (string) $request->param('id');

        return new StreamedResponse(
            fn () => $this->stream($jobId),
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    private function stream(string $jobId): void
    {
        $lastPayload = null;
        $deadline = microtime(true) + self::MAX_STREAM_SECONDS;

        while (microtime(true) < $deadline) {
            if (connection_aborted() === 1) {
                return;
            }

            $status = $this->jobStatus->get($jobId);

            if ($status !== null) {
                $payload = json_encode($status, JSON_THROW_ON_ERROR);

                if ($payload !== $lastPayload) {
                    echo "event: progress\n";
                    echo "data: {$payload}\n\n";
                    $lastPayload = $payload;
                }

                if (in_array($status['status'], ['done', 'failed'], true)) {
                    return;
                }
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }
    }

    /** @return array<string, mixed> */
    private function requireStatus(Request $request): array
    {
        $jobId = $request->param('id');
        $status = $jobId !== null ? $this->jobStatus->get($jobId) : null;

        if ($status === null) {
            throw new DomainException('Job not found.', DomainErrorType::NotFound);
        }

        return $status;
    }
}
