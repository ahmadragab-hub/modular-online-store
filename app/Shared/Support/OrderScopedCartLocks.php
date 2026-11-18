<?php

namespace App\Shared\Support;

use App\Domains\Cart\Models\Cart;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Support\Collection;

/**
 * Cart row locks for order-scoped inventory flows (ascending cart id).
 */
final class OrderScopedCartLocks
{
    public static function lockCartsForOrder(int $orderId, ?string $reservationStatus = null): void
    {
        $query = InventoryReservation::query()
            ->where('order_id', $orderId)
            ->whereNotNull('cart_id');

        if ($reservationStatus !== null) {
            $query->where('status', $reservationStatus);
        }

        $cartIds = $query
            ->distinct()
            ->orderBy('cart_id')
            ->pluck('cart_id');

        foreach ($cartIds as $cartId) {
            Cart::query()->whereKey($cartId)->lockForUpdate()->first();
        }
    }

    /**
     * @return Collection<int, InventoryReservation>
     */
    public static function lockReservationsForOrder(int $orderId, ?string $reservationStatus = null): Collection
    {
        $query = InventoryReservation::query()
            ->where('order_id', $orderId)
            ->orderBy('id');

        if ($reservationStatus !== null) {
            $query->where('status', $reservationStatus);
        }

        return $query->lockForUpdate()->get();
    }
}
