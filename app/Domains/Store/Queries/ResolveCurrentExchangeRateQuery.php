<?php

namespace App\Domains\Store\Queries;

use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\CurrencyExchangeRate;
use Carbon\CarbonInterface;

final class ResolveCurrentExchangeRateQuery
{
    /**
     * @return array{
     *     rate: string,
     *     currency_exchange_rate_id: ?int,
     *     effective_from: ?CarbonInterface
     * }
     */
    public function resolve(string $quoteCurrencyCode, ?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();

        $baseCode = Currency::query()->where('is_base', true)->value('code') ?? 'USD';

        if ($quoteCurrencyCode === $baseCode) {
            return [
                'rate' => '1.00000000',
                'currency_exchange_rate_id' => null,
                'effective_from' => null,
            ];
        }

        $rate = CurrencyExchangeRate::query()
            ->where('quote_currency_code', $quoteCurrencyCode)
            ->where('effective_from', '<=', $asOf)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($rate === null) {
            return [
                'rate' => '',
                'currency_exchange_rate_id' => null,
                'effective_from' => null,
            ];
        }

        return [
            'rate' => (string) $rate->rate,
            'currency_exchange_rate_id' => $rate->getKey(),
            'effective_from' => $rate->effective_from,
        ];
    }
}
