<?php

namespace App\Shared\Support;

final class MoneyMath
{
    public static function add(string $left, string $right, int $scale): string
    {
        return bcadd($left, $right, $scale);
    }

    public static function multiply(string $left, string $right, int $scale): string
    {
        return bcmul($left, $right, $scale);
    }

    public static function compare(string $left, string $right, int $scale = 16): int
    {
        return bccomp($left, $right, $scale);
    }

    /**
     * Half-up round a non-negative decimal amount to the given minor-unit scale.
     */
    public static function roundHalfUp(string $amount, int $minorUnits): string
    {
        if ($minorUnits < 0) {
            throw new \InvalidArgumentException('minorUnits must be non-negative.');
        }

        if (self::compare($amount, '0') < 0) {
            throw new \InvalidArgumentException('Checkout amounts must be non-negative.');
        }

        if ($minorUnits === 0) {
            $increment = '0.5';
        } else {
            $increment = '0.'.str_repeat('0', $minorUnits).'5';
        }

        return bcadd($amount, $increment, $minorUnits);
    }
}
