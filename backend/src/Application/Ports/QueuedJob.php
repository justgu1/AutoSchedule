<?php

declare(strict_types=1);

namespace App\Application\Ports;

/** Nome do trabalho, não da classe que o executa: quem enfileira não precisa conhecer o adapter. */
enum QueuedJob: string
{
    case ProcessDealershipPhoto = 'dealership.process-photo';
    case SendEmail = 'notification.send-email';
}
