<?php

namespace App\Http\Storefront;

use App\Shared\Support\MoneyMath;

final class StorefrontMoneyFormatter
{
    public static function format(string $amount, int $minorUnits): string
    {
        $normalized = MoneyMath::roundHalfUp(MoneyMath::add($amount, '0', $minorUnits + 2), $minorUnits);

        if (! str_contains($normalized, '.')) {
            $normalized = $minorUnits > 0
                ? $normalized.'.'.str_repeat('0', $minorUnits)
                : $normalized;
        }

        [$integerPart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '');
        $fractionPart = str_pad(substr($fractionPart, 0, $minorUnits), $minorUnits, '0');

        $negative = str_starts_with($integerPart, '-');
        if ($negative) {
            $integerPart = substr($integerPart, 1);
        }

        $integerPart = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integerPart) ?? $integerPart;
        $formatted = $minorUnits > 0 ? $integerPart.'.'.$fractionPart : $integerPart;

        return $negative ? '-'.$formatted : $formatted;
    }
}
