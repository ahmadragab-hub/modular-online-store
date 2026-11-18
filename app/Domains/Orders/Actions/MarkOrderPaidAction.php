<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Inventory\Actions\CommitReservationAction;
use App\Domains\Inventory\DTOs\CommitReservationInput;
use App\Domains\Inventory\Exceptions\RequiresActiveDatabaseTransactionException;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Shared\Support\OrderScopedCartLocks;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Internal use for verified payment success only (ADMIN-007).
 */
final class MarkOrderPaidAction
{
    public function __construct(
        private readonly CommitReservationAction $commitReservationAction,
    ) {}

    public function execute(int $orderId, ?int $actorUserId = null): Order
    {
        if (DB::transactionLevel() > 0) {
            return $this->markOrderPaidWithinTransaction($orderId, $actorUserId);
        }

        return DB::transaction(fn (): Order => $this->markOrderPaidWithinTransaction($orderId, $actorUserId));
    }

    private function markOrderPaidWithinTransaction(int $orderId, ?int $actorUserId): Order
    {
        if (DB::transactionLevel() < 1) {
            throw new RequiresActiveDatabaseTransactionException;
        }

        /** @var Order|null $order */
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

        if ($order === null) {
            throw new OrderTransitionException(
                OrderTransitionException::ORDER_NOT_FOUND,
                'Order not found.',
            );
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            throw new OrderTransitionException(
                OrderTransitionException::FORBIDDEN_TRANSITION,
                'Cancelled orders cannot be marked paid.',
            );
        }

        if ($order->status === Order::STATUS_PROCESSING) {
            $this->assertAllReservationsCommittedForOrder($orderId, lockCartsFirst: true);

            return $order->fresh();
        }

        if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
            throw new OrderTransitionException(
                OrderTransitionException::FORBIDDEN_TRANSITION,
                'Order is not awaiting payment.',
            );
        }

        $requiredByVariant = $this->requiredQuantitiesByVariant($orderId);

        OrderScopedCartLocks::lockCartsForOrder($orderId);

        /** @var Collection<int, InventoryReservation> $reservations */
        $reservations = OrderScopedCartLocks::lockReservationsForOrder($orderId);

        $this->assertReservationsReadyForCommit($requiredByVariant, $reservations);

        $fromStatus = $order->status;

        foreach ($reservations->sortBy('id') as $reservation) {
            if ($reservation->status !== InventoryReservation::STATUS_ACTIVE) {
                continue;
            }

            $this->commitReservationAction->execute(new CommitReservationInput(
                reservationId: (int) $reservation->getKey(),
            ));
        }

        $this->assertAllReservationsCommittedForOrder($orderId, lockCartsFirst: false);

        $order->status = Order::STATUS_PROCESSING;
        $order->paid_at = now();
        $order->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => $fromStatus,
            'to_status' => Order::STATUS_PROCESSING,
            'comment' => 'Payment verified.',
            'actor_user_id' => $actorUserId,
            'created_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * @return array<int, int> product_variant_id => quantity
     */
    private function requiredQuantitiesByVariant(int $orderId): array
    {
        $required = [];

        $items = OrderItem::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $variantId = (int) $item->product_variant_id;
            $required[$variantId] = ($required[$variantId] ?? 0) + (int) $item->quantity;
        }

        return $required;
    }

    /**
     * @param  array<int, int>  $requiredByVariant
     * @param  Collection<int, InventoryReservation>  $reservations
     */
    private function assertReservationsReadyForCommit(array $requiredByVariant, Collection $reservations): void
    {
        if ($requiredByVariant === []) {
            throw new OrderTransitionException(
                OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                'Order has no line items to commit.',
            );
        }

        if ($reservations->isEmpty()) {
            throw new OrderTransitionException(
                OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                'Order has no inventory reservations.',
            );
        }

        foreach ($reservations as $reservation) {
            if (in_array($reservation->status, [
                InventoryReservation::STATUS_EXPIRED,
                InventoryReservation::STATUS_RELEASED,
            ], true)) {
                throw new OrderTransitionException(
                    OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                    'Order has expired or released reservations.',
                );
            }

            if ($reservation->status === InventoryReservation::STATUS_COMMITTED) {
                throw new OrderTransitionException(
                    OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                    'Order has partially committed reservations.',
                );
            }
        }

        /** @var array<int, int> $activeByVariant */
        $activeByVariant = [];

        foreach ($reservations as $reservation) {
            if ($reservation->status !== InventoryReservation::STATUS_ACTIVE) {
                continue;
            }

            $variantId = (int) $reservation->product_variant_id;
            $activeByVariant[$variantId] = ($activeByVariant[$variantId] ?? 0) + (int) $reservation->quantity;
        }

        foreach ($requiredByVariant as $variantId => $quantity) {
            if (($activeByVariant[$variantId] ?? 0) !== $quantity) {
                throw new OrderTransitionException(
                    OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                    sprintf(
                        'Active reservation quantity for variant %d does not match order line quantity (%d required).',
                        $variantId,
                        $quantity,
                    ),
                );
            }
        }

        foreach (array_keys($activeByVariant) as $variantId) {
            if (! array_key_exists($variantId, $requiredByVariant)) {
                throw new OrderTransitionException(
                    OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                    'Order has reservations for variants that are not on the order.',
                );
            }
        }
    }

    private function assertAllReservationsCommittedForOrder(int $orderId, bool $lockCartsFirst): void
    {
        $requiredByVariant = $this->requiredQuantitiesByVariant($orderId);

        if ($lockCartsFirst) {
            OrderScopedCartLocks::lockCartsForOrder($orderId);
        }

        /** @var Collection<int, InventoryReservation> $reservations */
        $reservations = OrderScopedCartLocks::lockReservationsForOrder($orderId);

        if ($reservations->isEmpty()) {
            throw new OrderTransitionException(
                OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                'Order has no inventory reservations after commit.',
            );
        }

        /** @var array<int, int> $committedByVariant */
        $committedByVariant = [];

        foreach ($reservations as $reservation) {
            if ($reservation->status !== InventoryReservation::STATUS_COMMITTED) {
                throw new OrderTransitionException(
                    OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                    'Not all reservations are committed.',
                );
            }

            $variantId = (int) $reservation->product_variant_id;
            $committedByVariant[$variantId] = ($committedByVariant[$variantId] ?? 0) + (int) $reservation->quantity;
        }

        foreach ($requiredByVariant as $variantId => $quantity) {
            if (($committedByVariant[$variantId] ?? 0) !== $quantity) {
                throw new OrderTransitionException(
                    OrderTransitionException::RESERVATIONS_NOT_COMMITTABLE,
                    'Committed reservation quantities do not match order lines.',
                );
            }
        }
    }
}
