<?php

namespace App\Http\Controllers\Web\Admin\Store;

use App\Domains\Store\Actions\CreateExchangeRateAction;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\CreateExchangeRateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(): View
    {
        $rates = CurrencyExchangeRate::query()
            ->with(['quoteCurrency', 'createdByUser'])
            ->orderByDesc('effective_from')
            ->paginate(20);

        return view('admin.store.exchange-rates.index', compact('rates'));
    }

    public function store(CreateExchangeRateRequest $request, CreateExchangeRateAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $action->execute(
            quoteCurrencyCode: $validated['quote_currency_code'],
            rate: $validated['rate'],
            createdByUserId: (int) $request->user()->getKey(),
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('admin.exchange-rates.index')
            ->with('status', __('admin.store.exchange_rates.created'));
    }
}
