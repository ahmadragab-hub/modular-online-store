<?php

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\Exceptions\CartMutationException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Cart\Support\CartLineSnapshots;
use App\Domains\Cart\Support\CartMutationGuards;
use App\Domains\Catalog\Exceptions\VariantUnavailableForCartException;
use App\Domains\Catalog\Queries\ResolveVariantForCartLineQuery;
use App\Domains\Inventory\Queries\GetAvailableQuantityForVariantsQuery;
use Illuminate\Support\Facades\DB;

final class UpdateCartItemQuantityAction
{
    public function __construct(
        private readonly ResolveVariantForCartLineQuery $resolveVariantForCartLineQuery,
        private readonly GetAvailableQuantityForVariantsQuery $availableQuantityForVariantsQuery,
    ) {}

    public function execute(
        Cart $cart,
        int $cartItemId,
        int $quantity,
        string $contentLocale,
    ): CartItem {
        if ($quantity < 1) {
            throw new CartMutationException(
                CartMutationException::INVALID_QUANTITY,
                'Quantity must be at least 1.',
            );
        }

        return DB::transaction(function () use ($cart, $cartItemId, $quantity, $contentLocale): CartItem {
            /** @var Cart|null $lockedCart */
            $lockedCart = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->first();

            if ($lockedCart === null) {
                throw new CartMutationException(
                    CartMutationException::CART_NOT_ACTIVE,
                    'This cart is no longer active.',
                );
            }

            CartMutationGuards::assertActiveAndNotExpired($lockedCart);

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

            try {
                $variant = $this->resolveVariantForCartLineQuery->resolve((int) $item->product_variant_id, $contentLocale);
            } catch (VariantUnavailableForCartException) {
                throw new CartMutationException(
                    CartMutationException::VARIANT_UNAVAILABLE,
                    'The selected product variant is not available.',
                );
            }

            $available = $this->availableQuantityForVariantsQuery->quantitiesForVariantIds([$variant->getKey()]);
            $availableQuantity = $available[$variant->getKey()] ?? 0;

            if ($quantity > $availableQuantity) {
                throw new CartMutationException(
                    CartMutationException::INSUFFICIENT_STOCK,
                    'There is not enough stock for the requested quantity.',
                );
            }

            $snapshots = CartLineSnapshots::fromVariant($variant, $contentLocale);
            $item->fill(array_merge($snapshots, ['quantity' => $quantity]));
            $item->save();

            return $item->refresh();
        });
    }
}
