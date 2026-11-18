<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\DTOs\PaymentInitiationResult;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

final class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentGatewayContract $paymentGateway,
    ) {}

    public function execute(int $paymentId): PaymentInitiationResult
    {
        [$payment, $order] = $this->loadPaymentAndOrder($paymentId);

        if (
            $payment->status === Payment::STATUS_PENDING
            && $payment->provider_checkout_url !== null
            && $payment->provider === $this->paymentGateway->providerKey()
        ) {
            return new PaymentInitiationResult(
                provider: (string) $payment->provider,
                providerPaymentId: (string) $payment->provider_payment_id,
                redirectUrl: (string) $payment->provider_checkout_url,
                reusedExistingSession: true,
            );
        }

        $this->assertOrderEligibleForPayment($order);
        $this->assertPaymentEligibleForInitiation($payment);

        $this->paymentGateway->assertOperational();

        $initiation = $this->paymentGateway->initiateCheckout($payment, $order);

        DB::transaction(function () use ($payment, $initiation): void {
            /** @var Payment|null $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== Payment::STATUS_PENDING) {
                return;
            }

            $locked->provider = $initiation->provider;
            $locked->provider_payment_id = $initiation->providerPaymentId;
            $locked->provider_checkout_url = $initiation->redirectUrl;
            $locked->save();
        });

        return $initiation;
    }

    /**
     * @return array{0: Payment, 1: Order}
     */
    private function loadPaymentAndOrder(int $paymentId): array
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()->whereKey($paymentId)->first();

        if ($payment === null) {
            throw new PaymentOperationException(
                PaymentOperationException::PAYMENT_NOT_FOUND,
                'Payment not found.',
            );
        }

        /** @var Order|null $order */
        $order = Order::query()->whereKey($payment->order_id)->first();

        if ($order === null) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Payment order not found.',
            );
        }

        return [$payment, $order];
    }

    private function assertOrderEligibleForPayment(Order $order): void
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Cannot initiate payment for a cancelled order.',
            );
        }

        if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_FAILED], true)) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Order is not awaiting payment.',
            );
        }

        $hasActive = InventoryReservation::query()
            ->where('order_id', $order->getKey())
            ->where('status', InventoryReservation::STATUS_ACTIVE)
            ->exists();

        if (! $hasActive) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Order has no active inventory reservations.',
            );
        }
    }

    private function assertPaymentEligibleForInitiation(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Payment is not pending.',
            );
        }
    }
}
