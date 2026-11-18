<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Shared\Support\OrderScopedPaymentLocks;
use Illuminate\Support\Facades\DB;

/**
 * System path after reservation expiry: cancel unpaid order when no active holds remain (ADMIN-006 §F).
 *
 * Lock order when invoked: Order → Payment rows (ascending id). Callers must not acquire inventory locks
 * before this Action when payment rows for the order are not yet locked.
 */
final class CancelUnpaidOrderWhenHoldsClearedAction
{
    public function execute(int $orderId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RequiresActiveDatabaseTransactionException;
        }

        /** @var Order|null $order */
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

        if ($order === null) {
            return;
        }

        $payments = OrderScopedPaymentLocks::lockForOrder($orderId);

        if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
            return;
        }

        $hasActive = InventoryReservation::query()
            ->where('order_id', $orderId)
            ->where('status', InventoryReservation::STATUS_ACTIVE)
            ->exists();

        if ($hasActive) {
            return;
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return;
        }

        $fromStatus = $order->status;
        $order->status = Order::STATUS_CANCELLED;
        $order->cancelled_at = now();
        $order->save();

        OrderScopedPaymentLocks::cancelPendingPayments($payments);

        OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => $fromStatus,
            'to_status' => Order::STATUS_CANCELLED,
            'comment' => 'Order cancelled after reservation expiry.',
            'actor_user_id' => null,
            'created_at' => now(),
        ]);
    }
}
