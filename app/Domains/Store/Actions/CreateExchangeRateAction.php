<?php

namespace App\Domains\Store\Actions;

use App\Domains\Store\Exceptions\StoreAdminException;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\CurrencyExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateExchangeRateAction
{
    public function execute(
        string $quoteCurrencyCode,
        string $rate,
        int $createdByUserId,
        ?Carbon $effectiveFrom = null,
        ?string $notes = null,
    ): CurrencyExchangeRate {
        $quoteCurrencyCode = strtoupper(trim($quoteCurrencyCode));

        if (! Currency::query()->whereKey($quoteCurrencyCode)->exists()) {
            throw new StoreAdminException(
                StoreAdminException::INVALID_CURRENCY,
                'Quote currency is not configured.',
            );
        }

        return DB::transaction(function () use ($quoteCurrencyCode, $rate, $createdByUserId, $effectiveFrom, $notes): CurrencyExchangeRate {
            return CurrencyExchangeRate::query()->create([
                'quote_currency_code' => $quoteCurrencyCode,
                'rate' => $rate,
                'effective_from' => $effectiveFrom ?? now(),
                'notes' => $notes,
                'created_by_user_id' => $createdByUserId,
                'created_at' => now(),
            ]);
        });
    }
}
