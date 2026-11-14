<?php

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Cart\Support\CartMutationGuards;
use Illuminate\Support\Facades\DB;

final class RemoveCartItemAction
{
    public function execute(Cart $cart, int $cartItemId): void
    {
        DB::transaction(function () use ($cart, $cartItemId): void {
            /** @var Cart|null $lockedCart */
            $lockedCart = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->first();

            if ($lockedCart === null) {
                throw new CartMutationException(
                    CartMutationException::CART_NOT_ACTIVE,
                    'This cart is no longer active.',
                );
            }

            CartMutationGuards::assertActiveForRemoval($lockedCart);

            $item = CartItem::query()
                ->whereKey($cartItemId)
                ->where('cart_id', $lockedCart->getKey())
                ->first();

            if ($item === null) {
                throw new CartMutationException(
                    CartMutationException::CART_ITEM_NOT_FOUND,
                    'The cart item could not be found.',
                );
            }

            $item->delete();
        });
    }
}
