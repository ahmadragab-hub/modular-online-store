<?php

namespace App\Domains\Store\Services;

use App\Domains\Store\Models\Currency;
use App\Shared\Support\MoneyMath;

final class CheckoutAmountConverter
{
    /**
     * Convert a store catalog base-currency amount to the transaction currency using half-up rounding.
     *
     * {@see $exchangeRate} is quote currency units per 1 unit of catalog base currency.
     */
    public function convertBaseAmount(string $baseAmount, Currency $transactionCurrency, string $exchangeRate): string
    {
        $raw = MoneyMath::multiply($baseAmount, $exchangeRate, $transactionCurrency->minor_units + 4);

        return MoneyMath::roundHalfUp($raw, $transactionCurrency->minor_units);
    }

    /**
     * @deprecated Use {@see convertBaseAmount}; catalog base is EGP (Milestone 11).
     */
    public function convertUsdAmount(string $usdAmount, Currency $transactionCurrency, string $exchangeRate): string
    {
        return $this->convertBaseAmount($usdAmount, $transactionCurrency, $exchangeRate);
    }
}
