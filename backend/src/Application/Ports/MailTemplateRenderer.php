<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface MailTemplateRenderer
{
    /** @param array<string, string> $placeholders */
    public function render(string $templatePath, array $placeholders): string;
}
