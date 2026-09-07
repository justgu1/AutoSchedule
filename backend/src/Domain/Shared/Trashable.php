<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/** Entidade com lixeira reversível, pro purge agendado não precisar saber de qual domínio ela é. */
interface Trashable
{
    public string $id { get; }

    public TrashState $trash { get; }

    public function anonymized(): static;
}
