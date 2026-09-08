<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Availability\CreateAvailabilityException;
use App\Application\Availability\DeleteAvailabilityException;
use App\Application\Availability\ListAvailabilityExceptions;
use App\Application\Availability\UpdateAvailabilityException;
use App\Domain\Availability\AvailabilityException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

final readonly class AvailabilityExceptionController
{
    private const array RULES = [
        'dealership_id' => 'uuid',
        'vehicle_id' => 'uuid',
        'date' => 'required|date',
        'start_time' => 'time',
        'end_time' => 'time',
        'is_available' => 'required|boolean',
        'reason' => 'max:255',
    ];

    public function __construct(
        private ListAvailabilityExceptions $listExceptions,
        private CreateAvailabilityException $createException,
        private UpdateAvailabilityException $updateException,
        private DeleteAvailabilityException $deleteException,
    ) {
    }

    public function index(Request $request): Response
    {
        $exceptions = ($this->listExceptions)($request->query('dealership_id'), $request->query('vehicle_id'));

        return Response::success(array_map($this->toArray(...), $exceptions));
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::RULES);
        $exception = ($this->createException)($data, RequestActor::fromRequest($request));

        return Response::success($this->toArray($exception), 201);
    }

    public function update(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::RULES);
        $exception = ($this->updateException)($request->param('id'), $data, RequestActor::fromRequest($request));

        return Response::success($this->toArray($exception));
    }

    public function destroy(Request $request): Response
    {
        ($this->deleteException)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Availability exception removed.']);
    }

    /** @return array{id: string, dealership_id: ?string, vehicle_id: ?string, date: string, start_time: ?string, end_time: ?string, is_available: bool, reason: ?string} */
    private function toArray(AvailabilityException $exception): array
    {
        return [
            'id' => $exception->id,
            'dealership_id' => $exception->dealershipId,
            'vehicle_id' => $exception->vehicleId,
            'date' => $exception->date->format('Y-m-d'),
            'start_time' => $exception->startTime?->format('H:i'),
            'end_time' => $exception->endTime?->format('H:i'),
            'is_available' => $exception->isAvailable,
            'reason' => $exception->reason,
        ];
    }
}
