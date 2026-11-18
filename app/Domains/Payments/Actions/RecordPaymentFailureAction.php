<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Models\Payment;
use App\Shared\Support\OrderScopedPaymentLocks;
use Illuminate\Support\Facades\DB;

final class RecordPaymentFailureAction
{
    public function execute(int $paymentId, ?string $failureCode = null, ?string $failureMessage = null): Payment
    {
        $paymentStub = Payment::query()->whereKey($paymentId)->first();

        if ($paymentStub === null) {
            throw new PaymentOperationException(
                PaymentOperationException::PAYMENT_NOT_FOUND,
                'Payment not found.',
            );
        }

        $orderId = (int) $paymentStub->order_id;

        return DB::transaction(function () use ($paymentId, $orderId, $failureCode, $failureMessage): Payment {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Payment order not found.',
                );
            }

            $payments = OrderScopedPaymentLocks::lockForOrder($orderId);

            /** @var Payment|null $payment */
            $payment = $payments->firstWhere('id', $paymentId);

            if ($payment === null || (int) $payment->order_id !== $orderId) {
                throw new PaymentOperationException(
                    PaymentOperationException::PAYMENT_NOT_FOUND,
                    'Payment not found.',
                );
            }

            if ($order->status === Order::STATUS_CANCELLED) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Cannot record failure for a cancelled order.',
                );
            }

            if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Order is not awaiting payment.',
                );
            }

            $fromStatus = $order->status;

            $payment->status = Payment::STATUS_FAILED;
            $payment->failure_code = $failureCode;
            $payment->failure_message = $failureMessage;
            $payment->failed_at = now();
            $payment->save();

            if ($fromStatus !== Order::STATUS_PAYMENT_FAILED) {
                $order->status = Order::STATUS_PAYMENT_FAILED;
                $order->save();

                OrderStatusHistory::query()->create([
                    'order_id' => $order->getKey(),
                    'from_status' => $fromStatus,
                    'to_status' => Order::STATUS_PAYMENT_FAILED,
                    'comment' => 'Verified payment failure.',
                    'actor_user_id' => null,
                    'created_at' => now(),
                ]);
            }

            return $payment->fresh();
        });
    }
}
