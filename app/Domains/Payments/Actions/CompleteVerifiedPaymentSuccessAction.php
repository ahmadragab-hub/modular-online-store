<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Orders\Actions\MarkOrderPaidAction;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Models\Payment;
use App\Shared\Support\OrderScopedPaymentLocks;
use Illuminate\Support\Facades\DB;

final class CompleteVerifiedPaymentSuccessAction
{
    public function __construct(
        private readonly MarkOrderPaidAction $markOrderPaidAction,
    ) {}

    public function execute(int $paymentId, ?string $providerPaymentId = null): Payment
    {
        $paymentStub = Payment::query()->whereKey($paymentId)->first();

        if ($paymentStub === null) {
            throw new PaymentOperationException(
                PaymentOperationException::PAYMENT_NOT_FOUND,
                'Payment not found.',
            );
        }

        $orderId = (int) $paymentStub->order_id;

        return DB::transaction(function () use ($paymentId, $orderId, $providerPaymentId): Payment {
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

            if (in_array($payment->status, [Payment::STATUS_CAPTURED, Payment::STATUS_AUTHORIZED], true)) {
                return $payment;
            }

            if (! in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_FAILED], true)) {
                throw new PaymentOperationException(
                    PaymentOperationException::INVALID_STATE,
                    'Payment is not in a payable state.',
                );
            }

            $this->markOrderPaidAction->execute($orderId);

            $payment->status = Payment::STATUS_CAPTURED;
            $payment->provider_payment_id = $providerPaymentId;
            $payment->captured_at = now();
            $payment->save();

            return $payment->fresh();
        });
    }
}
