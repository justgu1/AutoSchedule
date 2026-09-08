<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Ports\MailTemplateRenderer;
use App\Application\Ports\Queue;
use App\Application\Ports\QueuedJob;
use App\Domain\Auth\PasswordResetToken;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;
use App\Domain\Shared\Email;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

/** Mesma resposta exista ou não a conta: não vaza cadastro, igual `LoginWithPassword`. */
final readonly class RequestPasswordReset
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $passwordResetTokens,
        private MailTemplateRenderer $mailTemplates,
        private Queue $queue,
        private int $passwordResetTtl,
        private string $frontendUrl,
        private string $templatePath,
    ) {
    }

    public function __invoke(string $email): void
    {
        $user = $this->users->findByEmail(new Email($email));

        if (!$user instanceof User) {
            return;
        }

        [$rawToken, $token] = PasswordResetToken::issue($user->id, $this->passwordResetTtl);
        $this->passwordResetTokens->insert($token);

        $html = $this->mailTemplates->render($this->templatePath, [
            'RESET_LINK' => sprintf('%s/reset-password?token=%s', $this->frontendUrl, $rawToken),
            'EXPIRES_MINUTES' => (string) intdiv($this->passwordResetTtl, 60),
        ]);

        $this->queue->push(QueuedJob::SendEmail, [
            'to' => $user->email->value,
            'subject' => 'Redefinir senha -- AutoSchedule',
            'html_body' => $html,
        ]);
    }
}
