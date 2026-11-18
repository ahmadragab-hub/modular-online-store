<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Support\Facades\DB;

final class InitializeVariantStockAction
{
    public function execute(int $productVariantId, int $quantityOnHand = 0): InventoryLevel
    {
        return DB::transaction(function () use ($productVariantId, $quantityOnHand): InventoryLevel {
            $existing = InventoryLevel::query()
                ->where('product_variant_id', $productVariantId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return InventoryLevel::query()->create([
                'product_variant_id' => $productVariantId,
                'quantity_on_hand' => max(0, $quantityOnHand),
                'quantity_reserved' => 0,
            ]);
        });
    }
}
