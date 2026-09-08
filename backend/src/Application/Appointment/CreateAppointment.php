<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Availability\ListAvailableSlots;
use App\Application\Dealership\DealershipFinder;
use App\Application\Ports\MailTemplateRenderer;
use App\Application\Ports\Queue;
use App\Application\Ports\QueuedJob;
use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Email;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;

/**
 * Cliente não loga -- vira `User` role `customer` achado ou criado por e-mail, mesmo mecanismo de
 * `LoginWithGoogle`. O snapshot fica no próprio agendamento e nunca sobrescreve o perfil existente.
 */
final readonly class CreateAppointment
{
    public function __construct(
        private VehicleFinder $vehicles,
        private DealershipFinder $dealerships,
        private UserRepository $users,
        private AppointmentRepository $appointments,
        private ListAvailableSlots $listAvailableSlots,
        private AuditLogger $audit,
        private Transaction $transaction,
        private Queue $queue,
        private MailTemplateRenderer $mailTemplates,
        private string $staffNotificationTemplatePath,
    ) {
    }

    public function __invoke(
        string $vehicleId,
        \DateTimeImmutable $scheduledAt,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        ActorContext $context,
    ): Appointment {
        $vehicle = $this->vehicles->findOrFail($vehicleId);
        $dealership = $this->dealerships->findOrFail($vehicle->dealershipId);
        $email = new Email($customerEmail);

        $this->assertSlotIsFree($vehicle->id, $scheduledAt);

        $appointment = $this->transaction->run(function () use ($vehicle, $email, $customerName, $customerPhone, $scheduledAt): Appointment {
            $customer = $this->users->findByEmail($email);

            if (!$customer instanceof User) {
                $customer = User::register($customerName, $email, $customerPhone, bin2hex(random_bytes(32)), UserRole::Customer);
                $this->users->insert($customer);
            }

            $appointment = Appointment::request($vehicle->id, $customer->id, $scheduledAt, $customerName, $email, $customerPhone);
            $this->appointments->insert($appointment);

            return $appointment;
        });

        $this->audit->record($context->audits(AuditEvent::AppointmentCreated, $appointment->id));

        $owner = $this->users->findById($dealership->ownerUserId);

        if ($owner instanceof User) {
            $html = $this->mailTemplates->render($this->staffNotificationTemplatePath, [
                'CUSTOMER_NAME' => $appointment->customerName,
                'SCHEDULED_AT' => $appointment->scheduledAt->format('d/m/Y H:i'),
            ]);

            $this->queue->push(QueuedJob::SendEmail, [
                'to' => $owner->email->value,
                'subject' => 'Novo agendamento -- AutoSchedule',
                'html_body' => $html,
            ]);
        }

        return $appointment;
    }

    /**
     * Reconfere o slot antes de gravar -- a checagem que rodou pro cliente escolher pode ter ficado velha.
     * Compara pelo instante, não pelo `format()`: fusos diferentes formatam string diferente.
     */
    private function assertSlotIsFree(string $vehicleId, \DateTimeImmutable $scheduledAt): void
    {
        $wanted = $scheduledAt->getTimestamp();
        $slots = ($this->listAvailableSlots)($vehicleId, $scheduledAt);

        $isFree = array_any($slots, static fn (\DateTimeImmutable $slot): bool => $slot->getTimestamp() === $wanted);

        if (!$isFree) {
            throw new DomainException('This time slot is no longer available.', DomainErrorType::Conflict);
        }
    }
}
