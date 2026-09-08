<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Application\Ports\MailTemplateRenderer;
use App\Application\Ports\Queue;
use App\Application\Ports\QueuedJob;
use App\Application\Ports\Transaction;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\AppointmentStatus;
use App\Domain\Appointment\Ports\AppointmentRepository;

/**
 * Decide QUANDO o e-mail de confirmação sai: na hora, se nada ocupa o veículo antes; ou
 * `released_at + 15min` de preparo, se houver um agendamento anterior ainda em aberto.
 */
final readonly class SendAppointmentConfirmationEmailsTask implements ScheduledTask
{
    private const int PREP_BUFFER_MINUTES = 15;

    public function __construct(
        private AppointmentRepository $appointments,
        private Transaction $transaction,
        private Queue $queue,
        private MailTemplateRenderer $mailTemplates,
        private string $templatePath,
        private string $frontendUrl,
        private int $pendingTtlSeconds,
    ) {
    }

    public function name(): string
    {
        return 'send-appointment-confirmation-emails';
    }

    public function dueIntervalSeconds(): int
    {
        return 60;
    }

    public function run(): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->appointments->findPendingAwaitingConfirmationEmail() as $appointment) {
            if (!$this->vehicleIsUnblockedFor($appointment, $now)) {
                continue;
            }

            [$rawToken, $updated] = $this->transaction->run(fn (): array => $appointment->markConfirmationSent($now, $this->pendingTtlSeconds));
            $this->appointments->update($updated);
            $this->sendEmail($updated, $rawToken);
        }
    }

    private function vehicleIsUnblockedFor(Appointment $appointment, \DateTimeImmutable $now): bool
    {
        $preceding = $this->appointments->findImmediatelyPreceding($appointment->vehicleId, $appointment->scheduledAt);

        if (!$preceding instanceof Appointment) {
            return true;
        }

        if (in_array($preceding->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
            return true;
        }

        return $preceding->releasedAt instanceof \DateTimeImmutable
            && $now >= $preceding->releasedAt->modify('+' . self::PREP_BUFFER_MINUTES . ' minutes');
    }

    private function sendEmail(Appointment $appointment, string $rawToken): void
    {
        $html = $this->mailTemplates->render($this->templatePath, [
            'CUSTOMER_NAME' => $appointment->customerName,
            'SCHEDULED_AT' => $appointment->scheduledAt->format('d/m/Y H:i'),
            // Link único pra uma página da SPA com os dois botões -- clique de e-mail nunca muta direto
            // (scanner de segurança pré-carrega GET), a mutação só acontece dentro da página.
            'MANAGE_LINK' => sprintf('%s/agendamentos/%s/confirmar?token=%s', $this->frontendUrl, $appointment->id, $rawToken),
        ]);

        $this->queue->push(QueuedJob::SendEmail, [
            'to' => $appointment->customerEmail->value,
            'subject' => 'Confirme seu teste-drive -- AutoSchedule',
            'html_body' => $html,
        ]);
    }
}
