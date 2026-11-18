<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Inventory\Actions\ReleaseReservationAction;
use App\Domains\Inventory\DTOs\ReleaseReservationInput;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Shared\Support\OrderScopedCartLocks;
use App\Shared\Support\OrderScopedPaymentLocks;
use Illuminate\Support\Facades\DB;

final class CancelOrderAction
{
    public function __construct(
        private readonly ReleaseReservationAction $releaseReservationAction,
    ) {}

    public function execute(int $orderId, ?int $actorUserId, ?string $comment = null): Order
    {
        return DB::transaction(function () use ($orderId, $actorUserId, $comment): Order {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                throw new OrderTransitionException(
                    OrderTransitionException::ORDER_NOT_FOUND,
                    'Order not found.',
                );
            }

            if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
                throw new OrderTransitionException(
                    OrderTransitionException::FORBIDDEN_TRANSITION,
                    'Only unpaid orders may be cancelled.',
                );
            }

            $payments = OrderScopedPaymentLocks::lockForOrder((int) $order->getKey());

            OrderScopedCartLocks::lockCartsForOrder($orderId, InventoryReservation::STATUS_ACTIVE);

            $fromStatus = $order->status;

            $reservations = OrderScopedCartLocks::lockReservationsForOrder(
                $orderId,
                InventoryReservation::STATUS_ACTIVE,
            );

            foreach ($reservations as $reservation) {
                $reservationId = (int) $reservation->getKey();
                $this->releaseReservationAction->execute(new ReleaseReservationInput(
                    reservationId: (int) $reservationId,
                ));
            }

            OrderScopedPaymentLocks::cancelPendingPayments($payments);

            $order->status = Order::STATUS_CANCELLED;
            $order->cancelled_at = now();
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->getKey(),
                'from_status' => $fromStatus,
                'to_status' => Order::STATUS_CANCELLED,
                'comment' => $comment ?? 'Order cancelled.',
                'actor_user_id' => $actorUserId,
                'created_at' => now(),
            ]);

            return $order->fresh();
        });
    }
}
