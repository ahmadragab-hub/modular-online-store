<?php

namespace App\Domains\Inventory\Queries;

use App\Domains\Inventory\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListInventoryMovementsForAdminQuery
{
    /**
     * @return LengthAwarePaginator<int, InventoryMovement>
     */
    public function execute(?int $productVariantId = null, int $perPage = 30): LengthAwarePaginator
    {
        $query = InventoryMovement::query()
            ->with(['productVariant', 'createdByUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($productVariantId !== null && $productVariantId > 0) {
            $query->where('product_variant_id', $productVariantId);
        }

        return $query->paginate($perPage);
    }
}
