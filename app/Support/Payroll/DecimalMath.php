<?php

namespace App\Support\Payroll;

use InvalidArgumentException;

final class DecimalMath
{
    public const SCALE = 4;

    private const FACTOR = 10000;

    public static function normalize(string|int $value): string
    {
        return self::format(self::toScaled($value));
    }

    public static function add(string|int ...$values): string
    {
        $total = 0;
        foreach ($values as $value) {
            $total += self::toScaled($value);
        }

        return self::format($total);
    }

    public static function subtract(string|int $left, string|int $right): string
    {
        return self::format(self::toScaled($left) - self::toScaled($right));
    }

    public static function multiplyInteger(string|int $value, int $multiplier): string
    {
        return self::format(self::toScaled($value) * $multiplier);
    }

    public static function multiply(string|int $left, string|int $right): string
    {
        $product = self::toScaled($left) * self::toScaled($right);
        $quotient = intdiv($product, self::FACTOR);
        $remainder = $product % self::FACTOR;
        if ($remainder * 2 >= self::FACTOR) {
            $quotient++;
        }

        return self::format($quotient);
    }

    public static function multiplyRatio(string|int $value, int $numerator, int $denominator): string
    {
        if ($denominator < 1 || $numerator < 0) {
            throw new InvalidArgumentException('Invalid decimal ratio.');
        }
        $product = self::toScaled($value) * $numerator;
        $quotient = intdiv($product, $denominator);
        $remainder = $product % $denominator;
        if ($remainder * 2 >= $denominator) {
            $quotient++;
        }

        return self::format($quotient);
    }

    public static function round(string|int $value, int $scale, string $mode): string
    {
        if ($scale < 0 || $scale > self::SCALE) {
            throw new InvalidArgumentException('Invalid decimal rounding scale.');
        }
        $scaled = self::toScaled($value);
        if ($scaled < 0) {
            throw new InvalidArgumentException('Payroll component values cannot be negative.');
        }
        $unit = 10 ** (self::SCALE - $scale);
        $quotient = intdiv($scaled, $unit);
        $remainder = $scaled % $unit;
        if ($mode === 'ceil' && $remainder > 0) {
            $quotient++;
        } elseif ($mode === 'nearest' && $remainder * 2 >= $unit) {
            $quotient++;
        }

        return self::format($quotient * $unit);
    }

    public static function compare(string|int $left, string|int $right): int
    {
        return self::toScaled($left) <=> self::toScaled($right);
    }

    public static function toScaled(string|int $value): int
    {
        $raw = trim((string) $value);
        if (! preg_match('/^-?\d{1,14}(?:\.\d{1,4})?$/', $raw)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }
        $negative = str_starts_with($raw, '-');
        $unsigned = ltrim($raw, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $scaled = ((int) $whole * self::FACTOR) + (int) str_pad($fraction, self::SCALE, '0');

        return $negative ? -$scaled : $scaled;
    }

    public static function format(int $scaled): string
    {
        $negative = $scaled < 0;
        $absolute = abs($scaled);
        $formatted = intdiv($absolute, self::FACTOR).'.'.str_pad((string) ($absolute % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
