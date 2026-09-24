<?php

namespace App;

class HikvisionEventType
{
    public static function label(?int $major, ?int $sub): ?string
    {
        if ($major !== 5) {
            return null;
        }

        return match ($sub) {
            75 => 'Authenticated via Face',
            76 => 'Face Authentication Failed',
            38 => 'Authenticated via Fingerprint',
            39 => 'Fingerprint Authentication Failed',
            default => null,
        };
    }

    public static function normalizeCode(mixed $value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $code = filter_var($value, FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 0,
            'max_range' => 2147483647,
        ]]);

        return $code === false ? null : $code;
    }
}
