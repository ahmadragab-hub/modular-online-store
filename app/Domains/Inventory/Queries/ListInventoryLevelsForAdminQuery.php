<?php

namespace App\Domains\Inventory\Queries;

use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListInventoryLevelsForAdminQuery
{
    /**
     * @return LengthAwarePaginator<int, InventoryLevel>
     */
    public function execute(?string $sku = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = InventoryLevel::query()
            ->with(['productVariant.product', 'productVariant.size', 'productVariant.color'])
            ->orderBy('product_variant_id');

        if ($sku !== null && trim($sku) !== '') {
            $query->whereHas('productVariant', fn ($q) => $q->where('sku', 'like', '%'.addcslashes(trim($sku), '%_\\').'%'));
        }

        return $query->paginate($perPage);
    }
}
