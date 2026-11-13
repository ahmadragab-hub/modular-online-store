<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Cart\Models\Cart;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use App\Domains\Inventory\DTOs\ExpireReservationsResult;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expires active reservations whose TTL has passed.
 *
 * Lock order per reservation (compatible with reserve/commit/release):
 * Cart (when present) → reservation row → inventory level.
 *
 * Each reservation is processed in its own transaction when no caller transaction is active.
 */
final class ExpireReservationsAction
{
    private const OUTCOME_EXPIRED = 'expired';

    private const OUTCOME_SKIPPED = 'skipped';

    public function execute(ExpireReservationsInput $input): ExpireReservationsResult
    {
        if ($input->batchLimit < 1) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVALID_QUANTITY,
                'Batch limit must be a positive integer.',
            );
        }

        $asOf = $input->asOf instanceof CarbonInterface
            ? $input->asOf
            : Carbon::parse($input->asOf ?? now());

        $candidateIds = InventoryReservation::query()
            ->where('status', InventoryReservation::STATUS_ACTIVE)
            ->where('expires_at', '<=', $asOf)
            ->orderBy('id')
            ->limit($input->batchLimit)
            ->pluck('id')
            ->all();

        $expiredIds = [];
        $skippedIds = [];
        $failedIds = [];

        foreach ($candidateIds as $reservationId) {
            try {
                $outcome = $this->runExpireOneInTransaction((int) $reservationId, $asOf);

                if ($outcome === self::OUTCOME_EXPIRED) {
                    $expiredIds[] = (int) $reservationId;
                } else {
                    $skippedIds[] = (int) $reservationId;
                }
            } catch (\Throwable $exception) {
                $failedIds[] = (int) $reservationId;
                Log::warning('inventory.reservation.expire_failed', [
                    'reservation_id' => $reservationId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return new ExpireReservationsResult(
            candidatesSelected: count($candidateIds),
            expiredCount: count($expiredIds),
            skippedCount: count($skippedIds),
            failedCount: count($failedIds),
            expiredReservationIds: $expiredIds,
            skippedReservationIds: $skippedIds,
            failedReservationIds: $failedIds,
        );
    }

    private function runExpireOneInTransaction(int $reservationId, CarbonInterface $asOf): string
    {
        if (DB::transactionLevel() > 0) {
            return $this->expireOne($reservationId, $asOf);
        }

        return DB::transaction(fn (): string => $this->expireOne($reservationId, $asOf));
    }

    private function expireOne(int $reservationId, CarbonInterface $asOf): string
    {
        if (DB::transactionLevel() < 1) {
            throw new RequiresActiveDatabaseTransactionException;
        }

        $reservationStub = InventoryReservation::query()->whereKey($reservationId)->first();

        if ($reservationStub === null) {
            return self::OUTCOME_SKIPPED;
        }

        if ($reservationStub->cart_id !== null) {
            Cart::query()->whereKey($reservationStub->cart_id)->lockForUpdate()->first();
        }

        $reservation = InventoryReservation::query()
            ->whereKey($reservationId)
            ->lockForUpdate()
            ->first();

        if ($reservation === null) {
            return self::OUTCOME_SKIPPED;
        }

        if ($reservation->status === InventoryReservation::STATUS_EXPIRED) {
            return self::OUTCOME_SKIPPED;
        }

        if ($reservation->status !== InventoryReservation::STATUS_ACTIVE) {
            return self::OUTCOME_SKIPPED;
        }

        if ($reservation->expires_at !== null && $reservation->expires_at->gt($asOf)) {
            return self::OUTCOME_SKIPPED;
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
                    'Cannot expire reservation %d: reserved counter (%d) is less than reservation quantity (%d).',
                    $reservation->getKey(),
                    $level->quantity_reserved,
                    $reservation->quantity,
                ),
            );
        }

        $quantity = $reservation->quantity;

        $reservation->status = InventoryReservation::STATUS_EXPIRED;
        $reservation->save();

        $level->quantity_reserved -= $quantity;
        $level->save();

        InventoryMovement::query()->create([
            'product_variant_id' => $reservation->product_variant_id,
            'type' => InventoryMovement::TYPE_RESERVATION_RELEASE,
            'quantity_delta' => 0,
            'quantity_on_hand_after' => $level->quantity_on_hand,
            'reference_type' => 'reservation',
            'reference_id' => $reservation->getKey(),
            'notes' => 'expired',
            'created_by_user_id' => null,
            'created_at' => now(),
        ]);

        return self::OUTCOME_EXPIRED;
    }
}
