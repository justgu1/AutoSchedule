<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;

/**
 * Só UUID: anúncio é estoque, não marca, e um slug feito de marca/modelo/ano mentiria a cada correção.
 * "Não é seu" e "não existe" viram o mesmo 404 de propósito: o RLS já esconde a linha alheia.
 */
final readonly class VehicleFinder
{
    public function __construct(private VehicleRepository $vehicles)
    {
    }

    public function findOrFail(?string $id): Vehicle
    {
        $vehicle = $id !== null ? $this->vehicles->findById($id) : null;

        if (!$vehicle instanceof Vehicle) {
            throw new DomainException('Vehicle not found.', DomainErrorType::NotFound);
        }

        return $vehicle;
    }
}
