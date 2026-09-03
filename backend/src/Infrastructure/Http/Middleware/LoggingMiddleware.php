<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

final class LoggingMiddleware implements Middleware
{
    /** @param callable(string): void $log */
    public function __construct(
        private $log = 'error_log',
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        ($this->log)(sprintf(
            '%s %s -> %d (%dms)',
            $request->method(),
            $request->path(),
            $response->status(),
            $durationMs,
        ));

        return $response;
    }
}
