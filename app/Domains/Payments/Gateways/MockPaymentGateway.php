<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\DTOs\PaymentInitiationResult;
use App\Domains\Payments\Exceptions\PaymentGatewayException;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\URL;

final class MockPaymentGateway implements PaymentGatewayContract
{
    public const PROVIDER = 'mock';

    public function providerKey(): string
    {
        return self::PROVIDER;
    }

    public function assertOperational(): void
    {
        if (app()->environment('production')) {
            throw new PaymentGatewayException(
                PaymentGatewayException::NOT_OPERATIONAL,
                'Mock payment gateway is disabled in production.',
            );
        }

        if (! config('payments.mock_enabled')) {
            throw new PaymentGatewayException(
                PaymentGatewayException::NOT_OPERATIONAL,
                'Mock payment gateway is disabled.',
            );
        }

        if (config('payments.driver') !== self::PROVIDER) {
            throw new PaymentGatewayException(
                PaymentGatewayException::NOT_OPERATIONAL,
                'Configured payment driver is not the mock gateway.',
            );
        }
    }

    public function initiateCheckout(Payment $payment, Order $order): PaymentInitiationResult
    {
        $this->assertOperational();

        $providerPaymentId = $payment->provider_payment_id
            ?? 'mock_'.hash('sha256', 'pay-'.$payment->getKey().'-'.$payment->idempotency_key);

        $redirectUrl = URL::temporarySignedRoute(
            'storefront.payments.mock.show',
            now()->addHours(2),
            ['payment' => $payment->getKey()],
        );

        return new PaymentInitiationResult(
            provider: self::PROVIDER,
            providerPaymentId: $providerPaymentId,
            redirectUrl: $redirectUrl,
            reusedExistingSession: $payment->provider_checkout_url !== null,
        );
    }
}
