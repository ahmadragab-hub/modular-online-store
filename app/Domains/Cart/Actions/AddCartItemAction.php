<?php

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\AddCartItemMergeLineResult;
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
            $lockedCart = $this->lockActiveCart($cart);

            return $this->addLineStrict($lockedCart, $productVariantId, $quantityDelta, $contentLocale);
        });
    }

    public function executeMergeLine(
        Cart $cart,
        int $productVariantId,
        int $quantityDelta,
        string $contentLocale,
    ): AddCartItemMergeLineResult {
        if ($quantityDelta < 1) {
            return new AddCartItemMergeLineResult(
                AddCartItemMergeLineResult::STATUS_UNAVAILABLE,
                $productVariantId,
                '',
                $quantityDelta,
                0,
                0,
            );
        }

        $lockedCart = $this->lockActiveCart($cart);

        try {
            $variant = $this->resolveVariantForCartLineQuery->resolve($productVariantId, $contentLocale);
        } catch (VariantUnavailableForCartException) {
            return new AddCartItemMergeLineResult(
                AddCartItemMergeLineResult::STATUS_UNAVAILABLE,
                $productVariantId,
                '',
                $quantityDelta,
                0,
                0,
            );
        }

        $productName = $variant->product?->name ?? $variant->sku;

        $existing = CartItem::query()
            ->where('cart_id', $lockedCart->getKey())
            ->where('product_variant_id', $variant->getKey())
            ->first();

        $existingQuantity = (int) ($existing?->quantity ?? 0);
        $requestedTotal = $existingQuantity + $quantityDelta;

        $available = $this->availableQuantityForVariantsQuery->quantitiesForVariantIds([$variant->getKey()]);
        $availableQuantity = $available[$variant->getKey()] ?? 0;

        if ($availableQuantity < 1) {
            return new AddCartItemMergeLineResult(
                AddCartItemMergeLineResult::STATUS_NO_STOCK,
                $variant->getKey(),
                $productName,
                $quantityDelta,
                0,
                $existingQuantity,
            );
        }

        $finalQuantity = min($requestedTotal, $availableQuantity);
        $quantityAdded = max(0, $finalQuantity - $existingQuantity);
        $status = AddCartItemMergeLineResult::STATUS_MERGED;

        if ($finalQuantity < $requestedTotal) {
            $status = AddCartItemMergeLineResult::STATUS_CAPPED;
        }

        if ($quantityAdded > 0) {
            $snapshots = CartLineSnapshots::fromVariant($variant, $contentLocale);

            if ($existing !== null) {
                $existing->fill(array_merge($snapshots, ['quantity' => $finalQuantity]));
                $existing->save();
            } else {
                CartItem::query()->create([
                    'cart_id' => $lockedCart->getKey(),
                    'product_variant_id' => $variant->getKey(),
                    'quantity' => $finalQuantity,
                    ...$snapshots,
                ]);
            }
        }

        return new AddCartItemMergeLineResult(
            $status,
            $variant->getKey(),
            $productName,
            $quantityDelta,
            $quantityAdded,
            $quantityAdded > 0 ? $finalQuantity : $existingQuantity,
        );
    }

    private function lockActiveCart(Cart $cart): Cart
    {
        /** @var Cart|null $lockedCart */
        $lockedCart = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->first();

        if ($lockedCart === null) {
            throw new CartMutationException(
                CartMutationException::CART_NOT_ACTIVE,
                'This cart is no longer active.',
            );
        }

        CartMutationGuards::assertActiveAndNotExpired($lockedCart);

        return $lockedCart;
    }

    private function addLineStrict(
        Cart $lockedCart,
        int $productVariantId,
        int $quantityDelta,
        string $contentLocale,
    ): CartItem {
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
    }
}
