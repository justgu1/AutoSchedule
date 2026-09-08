<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Ports\MailTemplateRenderer;
use App\Application\Ports\Queue;
use App\Application\Ports\QueuedJob;
use App\Domain\Appointment\Appointment;

/** Um template parametrizado pelo status, reaproveitado por confirmar/cancelar/concluir. */
final readonly class NotifyAppointmentStatusChanged
{
    public function __construct(
        private Queue $queue,
        private MailTemplateRenderer $mailTemplates,
        private string $templatePath,
    ) {
    }

    public function __invoke(Appointment $appointment, string $statusLabel): void
    {
        $html = $this->mailTemplates->render($this->templatePath, [
            'CUSTOMER_NAME' => $appointment->customerName,
            'SCHEDULED_AT' => $appointment->scheduledAt->format('d/m/Y H:i'),
            'STATUS_LABEL' => $statusLabel,
        ]);

        $this->queue->push(QueuedJob::SendEmail, [
            'to' => $appointment->customerEmail->value,
            'subject' => 'Atualização do seu agendamento -- AutoSchedule',
            'html_body' => $html,
        ]);
    }
}
