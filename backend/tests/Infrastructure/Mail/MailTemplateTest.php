<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Mail;

use App\Infrastructure\Mail\MailTemplate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailTemplateTest extends TestCase
{
    #[Test]
    public function substitui_todo_placeholder_presente(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-template');
        file_put_contents($path, '<p>Olá, {{NAME}}! Seu link: {{LINK}}</p>');

        $rendered = MailTemplate::render($path, ['NAME' => 'Ada', 'LINK' => 'https://example.com']);

        unlink($path);

        $this->assertSame('<p>Olá, Ada! Seu link: https://example.com</p>', $rendered);
    }

    #[Test]
    public function placeholder_sem_correspondente_no_template_e_ignorado(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-template');
        file_put_contents($path, '<p>Sem placeholder nenhum aqui.</p>');

        $rendered = MailTemplate::render($path, ['NAME' => 'Ada']);

        unlink($path);

        $this->assertSame('<p>Sem placeholder nenhum aqui.</p>', $rendered);
    }
}
