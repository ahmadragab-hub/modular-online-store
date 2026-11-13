<?php

namespace App\Domains\Inventory\Queries;

use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Support\Collection;

final class FindInventoryLevelsForVariantIdsQuery
{
    /**
     * Load inventory levels for the given variants in ascending `product_variant_id` order.
     *
     * Does not create rows, mutate quantities, or manage transactions. When {@see $lockForUpdate}
     * is true, row locks apply only if the caller has already started a database transaction.
     *
     * @param  list<int>  $productVariantIds
     * @return Collection<int, InventoryLevel>
     */
    public function find(array $productVariantIds, bool $lockForUpdate = false): Collection
    {
        $normalizedIds = $this->normalizeVariantIds($productVariantIds);

        if ($normalizedIds === []) {
            return new Collection;
        }

        $query = InventoryLevel::query()
            ->whereIn('product_variant_id', $normalizedIds)
            ->orderBy('product_variant_id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Collection<int, InventoryLevel> $levels */
        $levels = $query->get();

        if ($levels->count() !== count($normalizedIds)) {
            $foundIds = $levels->pluck('product_variant_id')->all();
            $missingIds = array_values(array_diff($normalizedIds, $foundIds));

            throw new MissingInventoryLevelException($missingIds);
        }

        return $levels;
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
