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

final class AddCartItemAction
{
    public function __construct(
        private readonly ResolveVariantForCartLineQuery $resolveVariantForCartLineQuery,
        private readonly GetAvailableQuantityForVariantsQuery $availableQuantityForVariantsQuery,
    ) {}

    public function execute(
        Cart $cart,
        int $productVariantId,
        int $quantityDelta,
        string $contentLocale,
    ): CartItem {
        if ($quantityDelta < 1) {
            throw new CartMutationException(
                CartMutationException::INVALID_QUANTITY,
                'Quantity must be at least 1.',
            );
        }

        return DB::transaction(function () use ($cart, $productVariantId, $quantityDelta, $contentLocale): CartItem {
            /** @var Cart|null $lockedCart */
            $lockedCart = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->first();

            if ($lockedCart === null) {
                throw new CartMutationException(
                    CartMutationException::CART_NOT_ACTIVE,
                    'This cart is no longer active.',
                );
            }

            CartMutationGuards::assertActiveAndNotExpired($lockedCart);

            try {
                $variant = $this->resolveVariantForCartLineQuery->resolve($productVariantId, $contentLocale);
            } catch (VariantUnavailableForCartException) {
                throw new CartMutationException(
                    CartMutationException::VARIANT_UNAVAILABLE,
                    'The selected product variant is not available.',
                );
            }

            $existing = CartItem::query()
                ->where('cart_id', $lockedCart->getKey())
                ->where('product_variant_id', $variant->getKey())
                ->first();

            $newQuantity = ($existing?->quantity ?? 0) + $quantityDelta;

            $available = $this->availableQuantityForVariantsQuery->quantitiesForVariantIds([$variant->getKey()]);
            $availableQuantity = $available[$variant->getKey()] ?? 0;

            if ($newQuantity > $availableQuantity) {
                throw new CartMutationException(
                    CartMutationException::INSUFFICIENT_STOCK,
                    'There is not enough stock for the requested quantity.',
                );
            }

            $snapshots = CartLineSnapshots::fromVariant($variant, $contentLocale);

            if ($existing !== null) {
                $existing->fill(array_merge($snapshots, ['quantity' => $newQuantity]));
                $existing->save();

                return $existing->refresh();
            }

            return CartItem::query()->create([
                'cart_id' => $lockedCart->getKey(),
                'product_variant_id' => $variant->getKey(),
                'quantity' => $newQuantity,
                ...$snapshots,
            ]);
        });
    }
}
