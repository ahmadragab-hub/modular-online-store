<?php

namespace App\Domains\Store\Queries;

use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\StoreSetting;

final class ResolveDisplayCurrencyCodeQuery
{
    public function resolve(?string $candidate = null): string
    {
        $candidate = $candidate !== null ? strtoupper(trim($candidate)) : '';

        if ($candidate !== '' && $this->isDisplayEnabled($candidate)) {
            return $candidate;
        }

        $settings = StoreSetting::singleton();
        $default = strtoupper((string) ($settings?->default_display_currency_code ?? 'USD'));

        if ($default !== '' && $this->isDisplayEnabled($default)) {
            return $default;
        }

        return 'USD';
    }

    private function isDisplayEnabled(string $code): bool
    {
        return Currency::query()
            ->whereKey($code)
            ->where('display_enabled', true)
            ->exists();
    }
}
