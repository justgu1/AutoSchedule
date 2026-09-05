<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

final class MailTemplate
{
    /**
     * Placeholder simples (`str_replace` em `{{CHAVE}}`) -- sem motor de
     * template novo pra um punhado de e-mails transacionais.
     *
     * @param array<string, string> $placeholders
     */
    public static function render(string $templatePath, array $placeholders): string
    {
        $template = file_get_contents($templatePath);

        $search = array_map(static fn (string $key): string => '{{' . $key . '}}', array_keys($placeholders));

        return str_replace($search, array_values($placeholders), $template);
    }
}
