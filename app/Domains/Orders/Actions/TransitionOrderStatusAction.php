<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

final class TransitionOrderStatusAction
{
    /**
     * Admin fulfillment transitions only (ADMIN-006).
     */
    private const ALLOWED = [
        Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED],
        Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
    ];

    public function execute(int $orderId, string $toStatus, int $actorUserId, ?string $comment = null): Order
    {
        return DB::transaction(function () use ($orderId, $toStatus, $actorUserId, $comment): Order {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                throw new OrderTransitionException(
                    OrderTransitionException::ORDER_NOT_FOUND,
                    'Order not found.',
                );
            }

            $fromStatus = $order->status;
            $allowedTargets = self::ALLOWED[$fromStatus] ?? [];

            if (! in_array($toStatus, $allowedTargets, true)) {
                throw new OrderTransitionException(
                    OrderTransitionException::FORBIDDEN_TRANSITION,
                    sprintf('Cannot transition order from %s to %s.', $fromStatus, $toStatus),
                );
            }

            $order->status = $toStatus;
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'comment' => $comment,
                'actor_user_id' => $actorUserId,
                'created_at' => now(),
            ]);

            return $order->fresh();
        });
    }
}
