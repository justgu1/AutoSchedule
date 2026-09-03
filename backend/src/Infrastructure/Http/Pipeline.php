<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Pipeline
{
    /** @param list<Middleware> $middleware */
    public function __construct(private readonly array $middleware = [])
    {
    }

    /** @param \Closure(Request): Response $destination */
    public function process(Request $request, \Closure $destination): Response
    {
        $chain = array_reduce(
            array_reverse($this->middleware),
            static fn (\Closure $next, Middleware $middleware): \Closure =>
                static fn (Request $req): Response => $middleware->handle($req, $next),
            $destination,
        );

        return $chain($request);
    }
}
