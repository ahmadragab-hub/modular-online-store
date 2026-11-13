<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Cart\Models\Cart;
use App\Domains\Inventory\DTOs\ReserveStockForCartInput;
use App\Domains\Inventory\DTOs\ReserveStockForCartLineResult;
use App\Domains\Inventory\DTOs\ReserveStockForCartResult;
use App\Domains\Inventory\Exceptions\ActiveCartReservationMismatchException;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InvalidStockReservationRequestException;
use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Queries\FindInventoryLevelsForVariantIdsQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reserves stock for checkout lines on a cart inside the caller's database transaction.
 *
 * Lock order (must be preserved to avoid deadlocks with other Inventory checkout paths):
 * 1. {@see Cart} row (`lockForUpdate`)
 * 2. Existing {@see InventoryReservation} rows for that cart (`lockForUpdate`)
 * 3. {@see InventoryLevel} rows by ascending `product_variant_id` (`lockForUpdate`)
 */
final class ReserveStockForCartAction
{
    public function __construct(
        private readonly FindInventoryLevelsForVariantIdsQuery $findInventoryLevelsForVariantIdsQuery,
    ) {}

    public function execute(ReserveStockForCartInput $input): ReserveStockForCartResult
    {
        if (DB::transactionLevel() < 1) {
            throw new RequiresActiveDatabaseTransactionException;
        }

        $expiresAt = $this->assertValidExpiry($input->expiresAt);
        $quantitiesByVariant = $this->buildRequestedQuantitiesMap($input);

        $cart = Cart::query()->whereKey($input->cartId)->lockForUpdate()->first();

        if ($cart === null) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::CART_NOT_FOUND,
                'Cart not found.',
            );
        }

        $this->assertCartEligibleForReservation($cart);

        /** @var Collection<int, InventoryReservation> $lockedActiveReservations */
        $lockedActiveReservations = InventoryReservation::query()
            ->where('cart_id', $cart->getKey())
            ->where('status', InventoryReservation::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get();

        if ($lockedActiveReservations->contains(
            fn (InventoryReservation $reservation): bool => $reservation->expires_at !== null && $reservation->expires_at->lte(now()),
        )) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::STALE_ACTIVE_RESERVATIONS,
                'Cart has active reservations that passed their expiry time. Wait for expiry processing before reserving again.',
            );
        }

        $activeReservations = $lockedActiveReservations->filter(
            fn (InventoryReservation $reservation): bool => $reservation->expires_at !== null && $reservation->expires_at->gt(now()),
        )->values();

        if ($activeReservations->contains(
            fn (InventoryReservation $reservation): bool => $reservation->order_id !== null,
        )) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::ACTIVE_RESERVATIONS_LINKED_TO_ORDER,
                'Cart has active reservations already linked to an order. Start a new checkout with a new cart.',
            );
        }

        $activeQuantitiesByVariant = $this->sumQuantitiesByVariant($activeReservations);

        if ($activeQuantitiesByVariant !== []) {
            if ($activeQuantitiesByVariant === $quantitiesByVariant) {
                return $this->buildReuseResult($cart->getKey(), $activeReservations);
            }

            throw new ActiveCartReservationMismatchException(
                cartId: $cart->getKey(),
                requestedQuantitiesByVariant: $quantitiesByVariant,
                activeQuantitiesByVariant: $activeQuantitiesByVariant,
            );
        }

        $variantIds = array_keys($quantitiesByVariant);

        /** @var Collection<int, InventoryLevel> $levels */
        $levels = $this->findInventoryLevelsForVariantIdsQuery->find($variantIds, lockForUpdate: true);

        $levelsByVariantId = $levels->keyBy('product_variant_id');

        foreach ($variantIds as $variantId) {
            $quantity = $quantitiesByVariant[$variantId];
            /** @var InventoryLevel $level */
            $level = $levelsByVariantId->get($variantId);

            $this->assertLevelCountersValid($level);

            $available = $level->quantity_on_hand - $level->quantity_reserved;

            if ($available < $quantity) {
                throw new InsufficientStockException(
                    productVariantId: $variantId,
                    requestedQuantity: $quantity,
                    availableQuantity: max(0, $available),
                );
            }
        }

        $createdLines = [];

        foreach ($variantIds as $variantId) {
            $quantity = $quantitiesByVariant[$variantId];
            /** @var InventoryLevel $level */
            $level = $levelsByVariantId->get($variantId);

            $reservation = InventoryReservation::query()->create([
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'status' => InventoryReservation::STATUS_ACTIVE,
                'cart_id' => $cart->getKey(),
                'order_id' => null,
                'expires_at' => $expiresAt,
            ]);

            $level->quantity_reserved += $quantity;
            $level->save();

            InventoryMovement::query()->create([
                'product_variant_id' => $variantId,
                'type' => InventoryMovement::TYPE_RESERVATION_HOLD,
                'quantity_delta' => 0,
                'quantity_on_hand_after' => $level->quantity_on_hand,
                'reference_type' => 'reservation',
                'reference_id' => $reservation->getKey(),
                'created_by_user_id' => null,
                'created_at' => now(),
            ]);

            $createdLines[] = new ReserveStockForCartLineResult(
                inventoryReservationId: $reservation->getKey(),
                productVariantId: $variantId,
                quantity: $quantity,
            );
        }

        return new ReserveStockForCartResult(
            cartId: $cart->getKey(),
            lines: $createdLines,
            reusedExistingReservations: false,
        );
    }

    private function assertCartEligibleForReservation(Cart $cart): void
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::CART_NOT_ACTIVE,
                'Cart is not active.',
            );
        }

        if ($cart->expires_at !== null && $cart->expires_at->isPast()) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::CART_EXPIRED,
                'Cart has expired.',
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function buildRequestedQuantitiesMap(ReserveStockForCartInput $input): array
    {
        if ($input->lines === []) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::EMPTY_LINES,
                'At least one variant line is required to reserve stock.',
            );
        }

        $quantitiesByVariant = [];
        $seenVariantIds = [];

        foreach ($input->lines as $line) {
            $variantId = $line->productVariantId;

            if ($variantId <= 0) {
                throw new InvalidStockReservationRequestException(
                    InvalidStockReservationRequestException::INVALID_QUANTITY,
                    'Product variant id must be a positive integer.',
                );
            }

            if (isset($seenVariantIds[$variantId])) {
                throw new InvalidStockReservationRequestException(
                    InvalidStockReservationRequestException::DUPLICATE_VARIANT,
                    "Duplicate variant id {$variantId} in reservation request.",
                );
            }

            $seenVariantIds[$variantId] = true;

            if ($line->quantity < 1) {
                throw new InvalidStockReservationRequestException(
                    InvalidStockReservationRequestException::INVALID_QUANTITY,
                    'Reservation quantity must be a positive integer.',
                );
            }

            $quantitiesByVariant[$variantId] = $line->quantity;
        }

        ksort($quantitiesByVariant, SORT_NUMERIC);

        return $quantitiesByVariant;
    }

    private function assertValidExpiry(\DateTimeInterface $expiresAt): CarbonInterface
    {
        $expiry = $expiresAt instanceof CarbonInterface
            ? $expiresAt
            : Carbon::parse($expiresAt);

        if (! $expiry->isFuture()) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVALID_EXPIRY,
                'Reservation expiry must be in the future.',
            );
        }

        return $expiry;
    }

    private function assertLevelCountersValid(InventoryLevel $level): void
    {
        if ($level->quantity_on_hand < 0 || $level->quantity_reserved < 0) {
            throw new InvalidStockReservationRequestException(
                InvalidStockReservationRequestException::INVALID_QUANTITY,
                sprintf(
                    'Inventory level for variant %d has invalid counter values.',
                    $level->product_variant_id,
                ),
            );
        }
    }

    /**
     * @param  Collection<int, InventoryReservation>  $reservations
     * @return array<int, int>
     */
    private function sumQuantitiesByVariant(Collection $reservations): array
    {
        $map = [];

        foreach ($reservations as $reservation) {
            $variantId = $reservation->product_variant_id;
            $map[$variantId] = ($map[$variantId] ?? 0) + $reservation->quantity;
        }

        ksort($map, SORT_NUMERIC);

        return $map;
    }

    /**
     * @param  Collection<int, InventoryReservation>  $activeReservations
     */
    private function buildReuseResult(int $cartId, Collection $activeReservations): ReserveStockForCartResult
    {
        $lines = $activeReservations
            ->sortBy([
                ['product_variant_id', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (InventoryReservation $reservation): ReserveStockForCartLineResult => new ReserveStockForCartLineResult(
                inventoryReservationId: $reservation->getKey(),
                productVariantId: $reservation->product_variant_id,
                quantity: $reservation->quantity,
            ))
            ->values()
            ->all();

        return new ReserveStockForCartResult(
            cartId: $cartId,
            lines: $lines,
            reusedExistingReservations: true,
        );
    }
}
