<?php

declare(strict_types=1);

namespace App\Domain\Users\Ports;

use App\Domain\Users\User;

interface UserRepository
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function existsByEmail(string $email): bool;

    public function insert(User $user): void;

    public function update(User $user): void;

    /** Anonymizes PII and soft-deletes the row (LGPD right to erasure). No-op if the user doesn't exist. */
    public function anonymizeAndSoftDelete(string $id): void;
}
