<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Cart\Models\Cart;
use App\Domains\Inventory\DTOs\CommitReservationInput;
use App\Domains\Inventory\DTOs\CommitReservationResult;
use App\Domains\Inventory\Exceptions\ExpiredInventoryReservationException;
use App\Domains\Inventory\Exceptions\InvalidInventoryReservationStateException;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Inventory\Exceptions\InventoryReservationNotFoundException;
use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Support\Facades\DB;

/**
 * Commits a single active inventory reservation (payment-success stock consumption).
 *
 * Lock order (compatible with {@see ReserveStockForCartAction}):
 * 1. {@see Cart} row when `cart_id` is set (`lockForUpdate`)
 * 2. {@see InventoryReservation} row (`lockForUpdate`)
 * 3. {@see InventoryLevel} for the variant (`lockForUpdate`)
 */
final class CommitReservationAction
{
    public function execute(CommitReservationInput $input): CommitReservationResult
    {
        if (DB::transactionLevel() < 1) {
            throw new RequiresActiveDatabaseTransactionException;
        }

        if ($input->reservationId < 1) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVALID_QUANTITY,
                'Reservation id must be a positive integer.',
            );
        }

        $reservationStub = InventoryReservation::query()->whereKey($input->reservationId)->first();

        if ($reservationStub === null) {
            throw new InventoryReservationNotFoundException(reservationId: $input->reservationId);
        }

        if ($reservationStub->cart_id !== null) {
            Cart::query()->whereKey($reservationStub->cart_id)->lockForUpdate()->first();
        }

        $reservation = InventoryReservation::query()
            ->whereKey($input->reservationId)
            ->lockForUpdate()
            ->first();

        if ($reservation === null) {
            throw new InventoryReservationNotFoundException(reservationId: $input->reservationId);
        }

        if ($reservation->status === InventoryReservation::STATUS_COMMITTED) {
            $level = InventoryLevel::query()
                ->where('product_variant_id', $reservation->product_variant_id)
                ->first();

            return new CommitReservationResult(
                reservationId: $reservation->getKey(),
                productVariantId: $reservation->product_variant_id,
                quantity: $reservation->quantity,
                quantityOnHandAfter: $level?->quantity_on_hand ?? 0,
                idempotentReplay: true,
            );
        }

        if ($reservation->status !== InventoryReservation::STATUS_ACTIVE) {
            throw new InvalidInventoryReservationStateException(
                reservationId: $reservation->getKey(),
                currentStatus: $reservation->status,
                operation: 'commit',
                expectedStatus: InventoryReservation::STATUS_ACTIVE,
            );
        }

        if ($reservation->expires_at !== null && $reservation->expires_at->lte(now())) {
            throw new ExpiredInventoryReservationException(
                reservationId: $reservation->getKey(),
                currentStatus: $reservation->status,
                expiresAt: $reservation->expires_at,
                operation: 'commit',
            );
        }

        if ($reservation->quantity < 1) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVALID_QUANTITY,
                'Reservation quantity must be a positive integer.',
            );
        }

        $level = InventoryLevel::query()
            ->where('product_variant_id', $reservation->product_variant_id)
            ->lockForUpdate()
            ->first();

        if ($level === null) {
            throw new MissingInventoryLevelException([$reservation->product_variant_id]);
        }

        if ($level->quantity_reserved < $reservation->quantity) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVENTORY_COUNTERS_INCONSISTENT,
                sprintf(
                    'Cannot commit reservation %d: reserved counter (%d) is less than reservation quantity (%d).',
                    $reservation->getKey(),
                    $level->quantity_reserved,
                    $reservation->quantity,
                ),
            );
        }

        if ($level->quantity_on_hand < $reservation->quantity) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVENTORY_COUNTERS_INCONSISTENT,
                sprintf(
                    'Cannot commit reservation %d: on-hand counter (%d) is less than reservation quantity (%d).',
                    $reservation->getKey(),
                    $level->quantity_on_hand,
                    $reservation->quantity,
                ),
            );
        }

        $quantity = $reservation->quantity;

        $reservation->status = InventoryReservation::STATUS_COMMITTED;
        $reservation->committed_at = now();
        $reservation->save();

        $level->quantity_on_hand -= $quantity;
        $level->quantity_reserved -= $quantity;
        $level->save();

        [$referenceType, $referenceId] = $this->movementReference($reservation);

        InventoryMovement::query()->create([
            'product_variant_id' => $reservation->product_variant_id,
            'type' => InventoryMovement::TYPE_SALE_COMMIT,
            'quantity_delta' => -$quantity,
            'quantity_on_hand_after' => $level->quantity_on_hand,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_user_id' => null,
            'created_at' => now(),
        ]);

        return new CommitReservationResult(
            reservationId: $reservation->getKey(),
            productVariantId: $reservation->product_variant_id,
            quantity: $quantity,
            quantityOnHandAfter: $level->quantity_on_hand,
            idempotentReplay: false,
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function movementReference(InventoryReservation $reservation): array
    {
        if ($reservation->order_id !== null) {
            return ['order', $reservation->order_id];
        }

        return ['reservation', $reservation->getKey()];
    }
}
