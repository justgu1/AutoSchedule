<?php

declare(strict_types=1);

namespace App\Infrastructure\Pagination;

final class PaginationPolicy
{
    public function __construct(
        public readonly int $defaultPerPage,
        public readonly int $maxPerPage,
    ) {
    }

    /** @return array{0: int, 1: int} [page, perPage], já com os limites aplicados */
    public function resolve(?string $page, ?string $perPage): array
    {
        $resolvedPage = max(1, (int) ($page ?? '1'));
        $resolvedPerPage = min($this->maxPerPage, max(1, (int) ($perPage ?? (string) $this->defaultPerPage)));

        return [$resolvedPage, $resolvedPerPage];
    }
}
