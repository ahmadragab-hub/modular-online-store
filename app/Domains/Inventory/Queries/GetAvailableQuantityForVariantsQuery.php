<?php

namespace App\Domains\Inventory\Queries;

use App\Domains\Checkout\Actions\PrepareCheckoutAction;
use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Support\Collection;

/**
 * Read-only storefront availability for product variants.
 *
 * Availability = max(0, quantity_on_hand - quantity_reserved). Missing inventory levels yield 0,
 * consistent with {@see PrepareCheckoutAction} read checks.
 */
final class GetAvailableQuantityForVariantsQuery
{
    /**
     * @param  list<int>  $productVariantIds
     * @return array<int, int> Map of product_variant_id => available quantity
     */
    public function quantitiesForVariantIds(array $productVariantIds): array
    {
        $normalizedIds = $this->normalizeVariantIds($productVariantIds);

        if ($normalizedIds === []) {
            return [];
        }

        /** @var Collection<int, InventoryLevel> $levels */
        $levels = InventoryLevel::query()
            ->whereIn('product_variant_id', $normalizedIds)
            ->get()
            ->keyBy('product_variant_id');

        $availability = [];

        foreach ($normalizedIds as $variantId) {
            $level = $levels->get($variantId);
            if ($level === null) {
                $availability[$variantId] = 0;

                continue;
            }

            $available = $level->quantity_on_hand - $level->quantity_reserved;
            $availability[$variantId] = max(0, $available);
        }

        return $availability;
    }

    /**
     * @param  list<int>  $productVariantIds
     * @return list<int>
     */
    private function normalizeVariantIds(array $productVariantIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productVariantIds)));
        sort($ids, SORT_NUMERIC);

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}
