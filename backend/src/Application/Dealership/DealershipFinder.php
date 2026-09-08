<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/**
 * O identificador aceita o UUID (uso interno/gerenciamento) ou o `slug`
 * (URL pública amigável) -- os dois formatos nunca colidem entre si. RLS
 * já barra dono errado (linha nem aparece), então "não é sua" e "não
 * existe" viram o mesmo 404, de propósito.
 */
final readonly class DealershipFinder
{
    public function __construct(private DealershipRepository $dealerships)
    {
    }

    public function findOrFail(?string $identifier): Dealership
    {
        $dealership = $identifier !== null ? $this->find($identifier) : null;

        if (!$dealership instanceof Dealership) {
            throw new DomainException('Dealership not found.', DomainErrorType::NotFound);
        }

        return $dealership;
    }

    private function find(string $identifier): ?Dealership
    {
        return $this->looksLikeUuid($identifier)
            ? $this->dealerships->findById($identifier)
            : $this->dealerships->findBySlug($identifier);
    }

    private function looksLikeUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
