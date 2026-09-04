<?php

declare(strict_types=1);

namespace App\Domain\Support;

final class Uuid
{
    /**
     * Gera um UUID versão 7 (RFC 9562): timestamp Unix em milissegundos (48 bits,
     * big-endian) seguido de bits aleatórios. Ordenável no tempo, então os ids
     * ordenam (e indexam) na ordem de inserção — diferente do v4, que é
     * totalmente aleatório. Usado por entidades que precisam ter um id antes
     * de persistir.
     */
    public static function v7(): string
    {
        $timestampMs = (int) (microtime(true) * 1000);
        $timeBytes = substr(pack('J', $timestampMs), 2, 6); // low 48 bits of the 64-bit big-endian value

        $bytes = $timeBytes . random_bytes(10);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70); // version 7
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 9562/4122

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
