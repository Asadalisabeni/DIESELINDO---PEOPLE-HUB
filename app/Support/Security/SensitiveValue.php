<?php

namespace App\Support\Security;

use Illuminate\Support\Str;

final class SensitiveValue
{
    public static function canonicalIdentifier(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    public static function blindIndex(?string $value, string $context): ?string
    {
        $canonical = self::canonicalIdentifier($value);

        if ($canonical === null) {
            return null;
        }

        $key = (string) config('security.blind_index_key');

        return hash_hmac('sha256', $context."\0".$canonical, $key);
    }

    public static function lastFour(?string $value): ?string
    {
        $canonical = self::canonicalIdentifier($value);

        return $canonical === null ? null : substr($canonical, -4);
    }

    public static function mask(?string $lastFour): string
    {
        return $lastFour ? '•••• '.$lastFour : '—';
    }
}
