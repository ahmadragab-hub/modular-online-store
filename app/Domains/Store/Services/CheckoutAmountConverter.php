<?php

namespace App\Domains\Store\Services;

use App\Domains\Store\Models\Currency;
use App\Shared\Support\MoneyMath;

final class CheckoutAmountConverter
{
    /**
     * Convert a USD catalog amount to transaction currency using half-up rounding.
     */
    public function convertUsdAmount(string $usdAmount, Currency $transactionCurrency, string $exchangeRate): string
    {
        $raw = MoneyMath::multiply($usdAmount, $exchangeRate, $transactionCurrency->minor_units + 4);

        return MoneyMath::roundHalfUp($raw, $transactionCurrency->minor_units);
    }
}
