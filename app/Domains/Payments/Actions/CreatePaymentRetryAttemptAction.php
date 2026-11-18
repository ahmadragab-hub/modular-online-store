<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

final class CreatePaymentRetryAttemptAction
{
    public function execute(int $orderId, string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($orderId, $idempotencyKey): Payment {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Order not found.',
                );
            }

            if ($order->status === Order::STATUS_CANCELLED) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Cannot retry payment for a cancelled order.',
                );
            }

            if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Order is not eligible for payment retry.',
                );
            }

            $hasActive = InventoryReservation::query()
                ->where('order_id', $orderId)
                ->where('status', InventoryReservation::STATUS_ACTIVE)
                ->exists();

            if (! $hasActive) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Order has no active inventory reservations.',
                );
            }

            $latestAttempt = (int) Payment::query()->where('order_id', $orderId)->max('attempt_number');
            $nextAttempt = $latestAttempt + 1;

            /** @var Payment|null $existing */
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                if ((int) $existing->order_id !== $orderId) {
                    throw new PaymentOperationException(
                        PaymentOperationException::INVALID_STATE,
                        'Payment idempotency key belongs to another order.',
                    );
                }

                return $existing;
            }

            return Payment::query()->create([
                'order_id' => $orderId,
                'attempt_number' => $nextAttempt,
                'amount' => $order->grand_total,
                'currency_code' => $order->currency_code,
                'status' => Payment::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
