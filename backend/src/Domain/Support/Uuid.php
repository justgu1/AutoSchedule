<?php

declare(strict_types=1);

namespace App\Domain\Support;

final class Uuid
{
    /**
     * Generates an RFC 9562 version 7 UUID: a 48-bit big-endian Unix millisecond
     * timestamp followed by random bits. Time-ordered, so ids sort (and index) in
     * insertion order — unlike v4, which is fully random. Used by entities that
     * must have an id before persistence.
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
