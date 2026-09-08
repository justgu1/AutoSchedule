<?php

declare(strict_types=1);

namespace App\Domain\Appointment;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Email;
use App\Domain\Shared\Uuid;

final readonly class Appointment
{
    /** Duração fixa -- não é campo que o cliente escolhe. */
    public const int DURATION_MINUTES = 60;

    public function __construct(
        public string $id,
        public string $vehicleId,
        public string $userId,
        public \DateTimeImmutable $scheduledAt,
        public string $customerName,
        public Email $customerEmail,
        public string $customerPhone,
        public AppointmentStatus $status,
        public ?string $confirmationTokenHash,
        public ?\DateTimeImmutable $confirmationEmailSentAt,
        public ?\DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $pickedUpAt,
        public ?\DateTimeImmutable $releasedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Sem token ainda -- ele só existe quando o e-mail de confirmação de fato sai (`markConfirmationSent()`),
     * senão o texto puro seria gerado e descartado sem ninguém poder usá-lo depois.
     */
    public static function request(
        string $vehicleId,
        string $userId,
        \DateTimeImmutable $scheduledAt,
        string $customerName,
        Email $customerEmail,
        string $customerPhone,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            vehicleId: $vehicleId,
            userId: $userId,
            scheduledAt: $scheduledAt,
            customerName: $customerName,
            customerEmail: $customerEmail,
            customerPhone: $customerPhone,
            status: AppointmentStatus::Pending,
            confirmationTokenHash: null,
            confirmationEmailSentAt: null,
            expiresAt: null,
            pickedUpAt: null,
            releasedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /** Prazo de devolução: sempre a partir do horário agendado, nunca de quando o veículo foi de fato retirado. */
    public function returnDeadline(): \DateTimeImmutable
    {
        return $this->scheduledAt->modify('+' . self::DURATION_MINUTES . ' minutes');
    }

    /**
     * O token nasce aqui, não em `request()`: texto puro gerado antes de existir e-mail pra levá-lo
     * seria descartado sem ninguém poder usá-lo depois. Só o hash é persistido.
     *
     * @return array{0: string, 1: self} token em texto puro, entidade pra persistir
     */
    #[\NoDiscard]
    public function markConfirmationSent(\DateTimeImmutable $now, int $ttlSeconds): array
    {
        $this->assertStatus(AppointmentStatus::Pending);

        if ($this->confirmationEmailSentAt instanceof \DateTimeImmutable) {
            throw new DomainException('Confirmation email already sent.', DomainErrorType::Conflict);
        }

        $rawToken = bin2hex(random_bytes(32));

        $updated = clone($this, [
            'confirmationTokenHash' => hash('sha256', $rawToken),
            'confirmationEmailSentAt' => $now,
            'expiresAt' => $now->modify("+{$ttlSeconds} seconds"),
            'updatedAt' => $now,
        ]);

        return [$rawToken, $updated];
    }

    #[\NoDiscard]
    public function confirm(): self
    {
        $this->assertStatus(AppointmentStatus::Pending);

        return clone($this, ['status' => AppointmentStatus::Confirmed, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /** Sem caminho de volta depois de confirmado -- só `pending` cancela. */
    #[\NoDiscard]
    public function cancel(): self
    {
        $this->assertStatus(AppointmentStatus::Pending);

        return clone($this, ['status' => AppointmentStatus::Cancelled, 'updatedAt' => new \DateTimeImmutable()]);
    }

    #[\NoDiscard]
    public function pickup(\DateTimeImmutable $now): self
    {
        $this->assertStatus(AppointmentStatus::Confirmed);

        if ($this->pickedUpAt instanceof \DateTimeImmutable) {
            throw new DomainException('Vehicle already picked up.', DomainErrorType::Conflict);
        }

        return clone($this, ['pickedUpAt' => $now, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /**
     * Serve tanto o clique manual do funcionário quanto a rotina automática -- a diferença é só o
     * `$at` que cada chamador passa (agora, ou o prazo de devolução).
     */
    #[\NoDiscard]
    public function release(\DateTimeImmutable $at): self
    {
        $this->assertStatus(AppointmentStatus::Confirmed);

        if (!$this->pickedUpAt instanceof \DateTimeImmutable) {
            throw new DomainException('Vehicle was never picked up.', DomainErrorType::Conflict);
        }

        if ($this->releasedAt instanceof \DateTimeImmutable) {
            throw new DomainException('Vehicle already released.', DomainErrorType::Conflict);
        }

        return clone($this, ['releasedAt' => $at, 'status' => AppointmentStatus::Completed, 'updatedAt' => new \DateTimeImmutable()]);
    }

    #[\NoDiscard]
    public function markNoShow(): self
    {
        $this->assertStatus(AppointmentStatus::Confirmed);

        if ($this->pickedUpAt instanceof \DateTimeImmutable) {
            throw new DomainException('Vehicle was picked up, this was not a no-show.', DomainErrorType::Conflict);
        }

        return clone($this, ['status' => AppointmentStatus::NoShow, 'updatedAt' => new \DateTimeImmutable()]);
    }

    private function assertStatus(AppointmentStatus $expected): void
    {
        if ($this->status !== $expected) {
            throw new DomainException(
                sprintf('Appointment must be "%s" for this action, is "%s".', $expected->value, $this->status->value),
                DomainErrorType::Conflict,
            );
        }
    }
}
