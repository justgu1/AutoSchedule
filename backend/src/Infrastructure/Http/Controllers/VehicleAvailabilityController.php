<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Availability\CreateVehicleAvailabilityRule;
use App\Application\Availability\DeleteVehicleAvailabilityRule;
use App\Application\Availability\ListAvailableDates;
use App\Application\Availability\ListAvailableSlots;
use App\Application\Availability\ListVehicleAvailabilityRules;
use App\Application\Availability\UpdateVehicleAvailabilityRule;
use App\Domain\Availability\VehicleAvailabilityRule;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

final readonly class VehicleAvailabilityController
{
    private const array RULES = ['weekday' => 'required|numeric|between:0,6', 'start_time' => 'required|time', 'end_time' => 'required|time'];

    public function __construct(
        private ListVehicleAvailabilityRules $listRules,
        private CreateVehicleAvailabilityRule $createRule,
        private UpdateVehicleAvailabilityRule $updateRule,
        private DeleteVehicleAvailabilityRule $deleteRule,
        private ListAvailableDates $listAvailableDates,
        private ListAvailableSlots $listAvailableSlots,
    ) {
    }

    public function index(Request $request): Response
    {
        $rules = ($this->listRules)($request->param('id'));

        return Response::success(array_map($this->toArray(...), $rules));
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::RULES);
        $rule = ($this->createRule)($request->param('id'), $data, RequestActor::fromRequest($request));

        return Response::success($this->toArray($rule), 201);
    }

    public function update(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::RULES);
        $rule = ($this->updateRule)($request->param('id'), $data, RequestActor::fromRequest($request));

        return Response::success($this->toArray($rule));
    }

    public function destroy(Request $request): Response
    {
        ($this->deleteRule)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Availability rule removed.']);
    }

    public function dates(Request $request): Response
    {
        $month = $this->parseMonth($request->query('month'));
        $dates = ($this->listAvailableDates)($request->param('id'), $month, $month->modify('first day of next month'));

        return Response::success(['dates' => array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $dates)]);
    }

    public function slots(Request $request): Response
    {
        $date = $this->parseDate($request->query('date'));
        $slots = ($this->listAvailableSlots)($request->param('id'), $date);

        return Response::success(['slots' => array_map(static fn (\DateTimeImmutable $s): string => $s->format('H:i'), $slots)]);
    }

    /** Sem `month`, o mês corrente -- é o que a tela abre mostrando por padrão. */
    private function parseMonth(?string $value): \DateTimeImmutable
    {
        if ($value === null) {
            return new \DateTimeImmutable('first day of this month');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m', $value);

        return $parsed instanceof \DateTimeImmutable
            ? $parsed
            : throw new DomainException('Invalid data.', DomainErrorType::Validation, ['month' => 'The month field must be in YYYY-MM format.']);
    }

    private function parseDate(?string $value): \DateTimeImmutable
    {
        $parsed = $value === null ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed instanceof \DateTimeImmutable
            ? $parsed
            : throw new DomainException('Invalid data.', DomainErrorType::Validation, ['date' => 'The date field must be in YYYY-MM-DD format.']);
    }

    /** @return array{id: string, vehicle_id: string, weekday: int, start_time: string, end_time: string} */
    private function toArray(VehicleAvailabilityRule $rule): array
    {
        return [
            'id' => $rule->id,
            'vehicle_id' => $rule->vehicleId,
            'weekday' => $rule->window->weekday,
            'start_time' => $rule->window->startTime->format('H:i'),
            'end_time' => $rule->window->endTime->format('H:i'),
        ];
    }
}
