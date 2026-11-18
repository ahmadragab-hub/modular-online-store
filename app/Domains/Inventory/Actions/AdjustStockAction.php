<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Exceptions\StockAdjustmentException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

final class AdjustStockAction
{
    public function execute(
        int $productVariantId,
        int $quantityDelta,
        int $actorUserId,
        ?string $notes = null,
    ): InventoryLevel {
        if ($quantityDelta === 0) {
            throw new StockAdjustmentException(
                StockAdjustmentException::INVALID_DELTA,
                'Adjustment quantity cannot be zero.',
            );
        }

        return DB::transaction(function () use ($productVariantId, $quantityDelta, $actorUserId, $notes): InventoryLevel {
            /** @var InventoryLevel|null $level */
            $level = InventoryLevel::query()
                ->where('product_variant_id', $productVariantId)
                ->lockForUpdate()
                ->first();

            if ($level === null) {
                throw new StockAdjustmentException(
                    StockAdjustmentException::LEVEL_NOT_FOUND,
                    'Inventory level not found for variant.',
                );
            }

            $newOnHand = $level->quantity_on_hand + $quantityDelta;

            if ($newOnHand < 0) {
                throw new StockAdjustmentException(
                    StockAdjustmentException::INSUFFICIENT_ON_HAND,
                    'Adjustment would make on-hand quantity negative.',
                );
            }

            if ($newOnHand < $level->quantity_reserved) {
                throw new StockAdjustmentException(
                    StockAdjustmentException::INSUFFICIENT_ON_HAND,
                    'Adjustment would make on-hand less than reserved quantity.',
                );
            }

            $level->quantity_on_hand = $newOnHand;
            $level->save();

            InventoryMovement::query()->create([
                'product_variant_id' => $productVariantId,
                'type' => $quantityDelta > 0
                    ? InventoryMovement::TYPE_ADJUSTMENT_IN
                    : InventoryMovement::TYPE_ADJUSTMENT_OUT,
                'quantity_delta' => abs($quantityDelta),
                'quantity_on_hand_after' => $newOnHand,
                'reference_type' => 'admin_adjustment',
                'reference_id' => null,
                'notes' => $notes,
                'created_by_user_id' => $actorUserId,
                'created_at' => now(),
            ]);

            return $level->fresh();
        });
    }
}
