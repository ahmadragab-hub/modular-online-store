<?php

namespace App\Domains\Store\Queries;

use App\Domains\Store\Models\Currency;
use Illuminate\Support\Collection;

final class ListDisplayEnabledCurrenciesQuery
{
    /**
     * @return Collection<int, Currency>
     */
    public function execute(): Collection
    {
        return Currency::query()
            ->where('display_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }
}
