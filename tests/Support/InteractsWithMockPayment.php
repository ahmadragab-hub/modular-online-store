<?php

namespace Tests\Support;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Gateways\MockPaymentGateway;
use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

trait InteractsWithMockPayment
{
    protected function enableMockPaymentGateway(): void
    {
        config([
            'payments.driver' => MockPaymentGateway::PROVIDER,
            'payments.mock_enabled' => true,
        ]);
    }

    protected function assertCheckoutRedirectsToMockPayment(TestResponse $response): void
    {
        $target = $response->headers->get('Location', '');

        $this->assertStringContainsString('/payments/mock/', $target);
    }

    protected function simulateMockPaymentSuccess(Order $order, int $attemptNumber = 1): TestResponse
    {
        $payment = Payment::query()
            ->where('order_id', $order->getKey())
            ->where('attempt_number', $attemptNumber)
            ->firstOrFail();

        $successUrl = URL::temporarySignedRoute(
            'storefront.payments.mock.success',
            now()->addHour(),
            ['payment' => $payment->getKey()],
        );

        return $this->post($successUrl);
    }

    protected function simulateMockPaymentFailure(Order $order, int $attemptNumber = 1): TestResponse
    {
        $payment = Payment::query()
            ->where('order_id', $order->getKey())
            ->where('attempt_number', $attemptNumber)
            ->firstOrFail();

        $failureUrl = URL::temporarySignedRoute(
            'storefront.payments.mock.failure',
            now()->addHour(),
            ['payment' => $payment->getKey()],
        );

        return $this->post($failureUrl);
    }
}
