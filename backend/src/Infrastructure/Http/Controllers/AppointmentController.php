<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Appointment\CancelAppointment;
use App\Application\Appointment\ConfirmAppointment;
use App\Application\Appointment\CreateAppointment;
use App\Application\Appointment\DTO\AppointmentSummary;
use App\Application\Appointment\ListAppointments;
use App\Application\Appointment\PickupAppointment;
use App\Application\Appointment\ReleaseAppointment;
use App\Domain\Appointment\Appointment;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

final readonly class AppointmentController
{
    public function __construct(
        private CreateAppointment $createAppointment,
        private ListAppointments $listAppointments,
        private ConfirmAppointment $confirmAppointment,
        private CancelAppointment $cancelAppointment,
        private PickupAppointment $pickupAppointment,
        private ReleaseAppointment $releaseAppointment,
        private PaginationPolicy $pagination,
    ) {
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'vehicle_id' => 'required|uuid',
            'scheduled_at' => 'required',
            'customer_name' => 'required|max:120',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|max:20',
        ]);

        $appointment = ($this->createAppointment)(
            $data->string('vehicle_id'),
            $this->parseDateTime($data->string('scheduled_at')),
            $data->string('customer_name'),
            $data->string('customer_email'),
            $data->string('customer_phone'),
            RequestActor::fromRequest($request),
        );

        return Response::success(AppointmentSummary::fromAppointment($appointment)->toArray(), 201);
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));
        $result = ($this->listAppointments)(RequestActor::fromRequest($request), $request->query('status'), $request->query('vehicle_id'), $perPage, ($page - 1) * $perPage);

        return Response::paginated(
            array_map(static fn (Appointment $appointment): array => AppointmentSummary::fromAppointment($appointment)->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    public function confirm(Request $request): Response
    {
        $token = Validator::validate($request->json(), ['token' => 'max:64'])->stringOrNull('token');
        $appointment = ($this->confirmAppointment)($request->param('id'), $token, RequestActor::fromRequest($request));

        return Response::success(AppointmentSummary::fromAppointment($appointment)->toArray());
    }

    public function cancel(Request $request): Response
    {
        $token = Validator::validate($request->json(), ['token' => 'max:64'])->stringOrNull('token');
        $appointment = ($this->cancelAppointment)($request->param('id'), $token, RequestActor::fromRequest($request));

        return Response::success(AppointmentSummary::fromAppointment($appointment)->toArray());
    }

    public function pickup(Request $request): Response
    {
        $appointment = ($this->pickupAppointment)($request->param('id'));

        return Response::success(AppointmentSummary::fromAppointment($appointment)->toArray());
    }

    public function release(Request $request): Response
    {
        $appointment = ($this->releaseAppointment)($request->param('id'), new \DateTimeImmutable(), RequestActor::fromRequest($request));

        return Response::success(AppointmentSummary::fromAppointment($appointment)->toArray());
    }

    private function parseDateTime(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['scheduled_at' => 'The scheduled_at field must be a valid date-time.']);
        }
    }
}
