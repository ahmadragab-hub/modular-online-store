<?php

namespace App\Domains\Cart\Support;

use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\Cart;

final class CartMutationGuards
{
    public static function assertActiveAndNotExpired(Cart $cart): void
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw new CartMutationException(
                CartMutationException::CART_NOT_ACTIVE,
                'This cart is no longer active.',
            );
        }

        if ($cart->expires_at !== null && $cart->expires_at->isPast()) {
            throw new CartMutationException(
                CartMutationException::CART_EXPIRED,
                'This cart has expired.',
            );
        }
    }

    public static function assertActiveForRemoval(Cart $cart): void
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw new CartMutationException(
                CartMutationException::CART_NOT_ACTIVE,
                'This cart is no longer active.',
            );
        }
    }
}
