<?php

namespace App\Http\Storefront;

use App\Domains\Cart\Actions\GetOrCreateCartAction;
use App\Domains\Cart\Actions\GetOrCreateCustomerCartAction;
use App\Domains\Cart\DTOs\GuestCartResult;
use App\Domains\Cart\Exceptions\MultipleActiveCustomerCartsException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Customers\Queries\FindCustomerForUserQuery;
use App\Domains\Identity\Models\User;
use App\Domains\Store\Queries\ResolveDisplayCurrencyCodeQuery;
use App\Shared\Support\GuestCartSession;
use Illuminate\Http\Request;

final class GuestCartResolver
{
    public function __construct(
        private readonly GetOrCreateCartAction $getOrCreateCartAction,
        private readonly GetOrCreateCustomerCartAction $getOrCreateCustomerCartAction,
        private readonly FindCustomerForUserQuery $findCustomerForUserQuery,
        private readonly ResolveDisplayCurrencyCodeQuery $resolveDisplayCurrencyCodeQuery,
    ) {}

    public function resolve(Request $request): GuestCartResult
    {
        $candidate = $request->attributes->get('storefront.display_currency');
        $candidate = is_string($candidate) ? $candidate : null;
        $displayCurrency = $this->resolveDisplayCurrencyCodeQuery->resolve($candidate);

        $user = $request->user();

        if ($user instanceof User && $user->role === User::ROLE_CUSTOMER && $user->isActive()) {
            $customer = $this->findCustomerForUserQuery->execute($user);

            if ($customer === null) {
                abort(403);
            }

            try {
                $cart = $this->getOrCreateCustomerCartAction->execute($customer, $user, $displayCurrency);
            } catch (MultipleActiveCustomerCartsException $exception) {
                abort(409, __('storefront.cart.errors.multiple_active_carts', [
                    'ids' => implode(', ', $exception->cartIds),
                ]));
            }

            return new GuestCartResult($cart, '', false);
        }

        $sessionToken = $request->session()->get(GuestCartSession::SESSION_KEY);
        $sessionToken = is_string($sessionToken) ? trim($sessionToken) : '';

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
