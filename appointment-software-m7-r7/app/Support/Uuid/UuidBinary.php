<?php

namespace App\Support\Uuid;

use InvalidArgumentException;

final class UuidBinary
{
    public static function toBytes(string $uuid): string
    {
        $hex = strtolower(str_replace('-', '', trim($uuid)));

        if (! preg_match('/^[0-9a-f]{32}$/', $hex)) {
            throw new InvalidArgumentException('Invalid UUID string.');
        }

        $bytes = hex2bin($hex);

        if ($bytes === false) {
            throw new InvalidArgumentException('Invalid UUID string.');
        }

        return $bytes;
    }

    public static function fromBytes(?string $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if (strlen($bytes) !== 16) {
            throw new InvalidArgumentException('Binary UUID must contain exactly 16 bytes.');
        }

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
