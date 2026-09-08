<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Notification\SendEmail;
use App\Application\Shared\ValidatedInput;

final readonly class SendEmailJob implements Job
{
    public function __construct(private SendEmail $sendEmail)
    {
    }

    public function handle(array $payload): void
    {
        $input = new ValidatedInput($payload);

        ($this->sendEmail)($input->string('to'), $input->string('subject'), $input->string('html_body'));
    }
}
