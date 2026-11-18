<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\Exceptions\PaymentGatewayException;
use App\Domains\Payments\Exceptions\PaymentOperationException;
use App\Domains\Payments\Gateways\MockPaymentGateway;
use App\Domains\Payments\Models\Payment;

final class ProcessMockPaymentSimulationAction
{
    public function __construct(
        private readonly PaymentGatewayContract $paymentGateway,
        private readonly CompleteVerifiedPaymentSuccessAction $completeVerifiedPaymentSuccessAction,
        private readonly RecordPaymentFailureAction $recordPaymentFailureAction,
    ) {}

    public function executeSuccess(int $paymentId): Payment
    {
        $this->assertMockSimulationAllowed($paymentId);

        return $this->completeVerifiedPaymentSuccessAction->execute(
            $paymentId,
            $this->resolvedProviderPaymentId($paymentId),
        );
    }

    public function executeFailure(int $paymentId): Payment
    {
        $this->assertMockSimulationAllowed($paymentId);

        return $this->recordPaymentFailureAction->execute(
            $paymentId,
            'MOCK_DECLINED',
            'Simulated payment failure.',
        );
    }

    private function assertMockSimulationAllowed(int $paymentId): void
    {
        $this->paymentGateway->assertOperational();

        if ($this->paymentGateway->providerKey() !== MockPaymentGateway::PROVIDER) {
            throw new PaymentGatewayException(
                PaymentGatewayException::NOT_OPERATIONAL,
                'Payment driver is not mock.',
            );
        }

        /** @var Payment|null $payment */
        $payment = Payment::query()->whereKey($paymentId)->first();

        if ($payment === null) {
            throw new PaymentOperationException(
                PaymentOperationException::PAYMENT_NOT_FOUND,
                'Payment not found.',
            );
        }

        if ($payment->provider !== MockPaymentGateway::PROVIDER) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Payment was not initiated through the mock gateway.',
            );
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            throw new PaymentOperationException(
                PaymentOperationException::INVALID_STATE,
                'Payment is not awaiting simulation.',
            );
        }
    }

    private function resolvedProviderPaymentId(int $paymentId): string
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()->whereKey($paymentId)->first();

        return (string) ($payment?->provider_payment_id ?? 'mock_'.$paymentId);
    }
}
