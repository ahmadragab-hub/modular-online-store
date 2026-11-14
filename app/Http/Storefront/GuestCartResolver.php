<?php

namespace App\Http\Storefront;

use App\Domains\Cart\Actions\GetOrCreateCartAction;
use App\Domains\Cart\DTOs\GuestCartResult;
use App\Domains\Cart\Models\Cart;
use App\Domains\Store\Queries\ResolveDisplayCurrencyCodeQuery;
use App\Shared\Support\GuestCartSession;
use Illuminate\Http\Request;

final class GuestCartResolver
{
    public function __construct(
        private readonly GetOrCreateCartAction $getOrCreateCartAction,
        private readonly ResolveDisplayCurrencyCodeQuery $resolveDisplayCurrencyCodeQuery,
    ) {}

    public function resolve(Request $request): GuestCartResult
    {
        $sessionToken = $request->session()->get(GuestCartSession::SESSION_KEY);
        $sessionToken = is_string($sessionToken) ? trim($sessionToken) : '';

        $candidate = $request->attributes->get('storefront.display_currency');
        $candidate = is_string($candidate) ? $candidate : null;
        $displayCurrency = $this->resolveDisplayCurrencyCodeQuery->resolve($candidate);

        $result = $this->getOrCreateCartAction->execute($sessionToken, $displayCurrency);

        $request->session()->put(GuestCartSession::SESSION_KEY, $result->sessionToken);

        return $result;
    }

    public function loadCartWithItems(Cart $cart): Cart
    {
        return $cart->load([
            'items.productVariant.product.images',
            'items.productVariant.size',
            'items.productVariant.color',
            'displayCurrency',
        ]);
    }
}
