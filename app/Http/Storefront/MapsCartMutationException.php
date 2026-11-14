<?php

namespace App\Http\Storefront;

use App\Domains\Cart\Exceptions\CartMutationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MapsCartMutationException
{
    public static function toRedirect(Request $request, CartMutationException $exception): RedirectResponse
    {
        return match ($exception->errorCode) {
            CartMutationException::INVALID_QUANTITY,
            CartMutationException::VARIANT_UNAVAILABLE,
            CartMutationException::INSUFFICIENT_STOCK,
            CartMutationException::CART_NOT_ACTIVE,
            CartMutationException::CART_EXPIRED => redirect()
                ->back()
                ->withInput()
                ->withErrors(self::fieldErrors($exception)),

            default => redirect()
                ->back()
                ->withErrors(['cart' => __('storefront.cart.errors.generic')]),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private static function fieldErrors(CartMutationException $exception): array
    {
        $message = match ($exception->errorCode) {
            CartMutationException::INVALID_QUANTITY => __('storefront.cart.errors.invalid_quantity'),
            CartMutationException::VARIANT_UNAVAILABLE => __('storefront.cart.errors.variant_unavailable'),
            CartMutationException::INSUFFICIENT_STOCK => __('storefront.cart.errors.insufficient_stock'),
            CartMutationException::CART_NOT_ACTIVE => __('storefront.cart.errors.cart_not_active'),
            CartMutationException::CART_EXPIRED => __('storefront.cart.errors.cart_expired'),
            default => __('storefront.cart.errors.generic'),
        };

        return match ($exception->errorCode) {
            CartMutationException::INVALID_QUANTITY => ['quantity' => [$message]],
            default => ['cart' => [$message]],
        };
    }
}
