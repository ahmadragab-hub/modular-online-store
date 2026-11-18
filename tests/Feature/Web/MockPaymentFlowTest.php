<?php

namespace Tests\Feature\Web;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Exceptions\PaymentGatewayException;
use App\Domains\Payments\Gateways\MockPaymentGateway;
use App\Domains\Payments\Models\Payment;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Domains\Store\Models\StoreSetting;
use App\Shared\Support\GuestCartSession;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Support\BuildsCheckoutPlaceOrderFixtures;
use Tests\Support\InteractsWithMockPayment;
use Tests\TestCase;

class MockPaymentFlowTest extends TestCase
{
    use BuildsCheckoutPlaceOrderFixtures;
    use InteractsWithMockPayment;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);

        config([
            'payments.driver' => MockPaymentGateway::PROVIDER,
            'payments.mock_enabled' => true,
        ]);

        StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->update([
            'shipping_enabled' => false,
        ]);
    }

    public function test_checkout_redirects_to_mock_payment_and_success_commits_order(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('1000.00');
        $cart = $this->createGuestCartWithItem($variant);

        $idempotency = hash('sha256', 'mock-pay-'.uniqid('', true));

        $response = $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), [
                'idempotency_key' => $idempotency,
                'transaction_currency_code' => 'EGP',
                'content_locale' => 'en',
                'guest_email' => 'buyer@example.test',
                'shipping_address' => $this->defaultAddresses()['shipping'],
                'billing_address' => $this->defaultAddresses()['billing'],
            ]);

        $response->assertRedirect();

        $order = Order::query()->where('idempotency_key', $idempotency)->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->getKey())->where('attempt_number', 1)->firstOrFail();

        $this->assertSame('EGP', $order->currency_code);
        $this->assertSame(MockPaymentGateway::PROVIDER, $payment->fresh()->provider);

        $successUrl = URL::temporarySignedRoute(
            'storefront.payments.mock.success',
            now()->addHour(),
            ['payment' => $payment->getKey()],
        );

        $this->post($successUrl)->assertRedirect(route('storefront.checkout.success', ['publicUuid' => $order->public_uuid]));

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        $this->assertSame(
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );
    }

    public function test_usd_checkout_requires_exchange_rate(): void
    {
        CurrencyExchangeRate::query()->where('quote_currency_code', 'USD')->delete();

        ['variant' => $variant] = $this->createPublishedSellableVariant('100.00');
        $cart = $this->createGuestCartWithItem($variant);

        $response = $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), [
                'idempotency_key' => hash('sha256', 'usd-missing-'.uniqid('', true)),
                'transaction_currency_code' => 'USD',
                'content_locale' => 'en',
                'guest_email' => 'buyer@example.test',
                'shipping_address' => $this->defaultAddresses()['shipping'],
                'billing_address' => $this->defaultAddresses()['billing'],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_usd_checkout_uses_configured_exchange_rate(): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'USD',
            'rate' => '0.02000000',
            'effective_from' => now()->subHour(),
            'created_at' => now(),
        ]);

        ['variant' => $variant] = $this->createPublishedSellableVariant('100.00');
        $cart = $this->createGuestCartWithItem($variant);
        $idempotency = hash('sha256', 'usd-rate-'.uniqid('', true));

        $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), [
                'idempotency_key' => $idempotency,
                'transaction_currency_code' => 'USD',
                'content_locale' => 'en',
                'guest_email' => 'buyer@example.test',
                'shipping_address' => $this->defaultAddresses()['shipping'],
                'billing_address' => $this->defaultAddresses()['billing'],
            ])->assertRedirect();

        $order = Order::query()->where('idempotency_key', $idempotency)->firstOrFail();
        $this->assertSame('USD', $order->currency_code);
        $this->assertSame('2.0000', $order->grand_total);
    }

    public function test_mock_simulation_disabled_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(PaymentGatewayException::class);

        $this->app->make(MockPaymentGateway::class)->assertOperational();
    }

    public function test_payment_failure_retains_active_reservation_and_allows_retry(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('500.00');
        $cart = $this->createGuestCartWithItem($variant);
        $idempotency = hash('sha256', 'mock-fail-'.uniqid('', true));

        $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), [
                'idempotency_key' => $idempotency,
                'transaction_currency_code' => 'EGP',
                'content_locale' => 'en',
                'guest_email' => 'fail@example.test',
                'shipping_address' => $this->defaultAddresses()['shipping'],
                'billing_address' => $this->defaultAddresses()['billing'],
            ])->assertRedirect();

        $order = Order::query()->where('idempotency_key', $idempotency)->firstOrFail();
        $this->simulateMockPaymentFailure($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_PAYMENT_FAILED, $order->status);
        $this->assertSame(
            InventoryReservation::STATUS_ACTIVE,
            InventoryReservation::query()->where('order_id', $order->getKey())->value('status'),
        );
        $this->assertSame(1, Payment::query()->where('order_id', $order->getKey())->count());

        $retryKey = hash('sha256', 'retry-'.uniqid('', true));
        $this->post(route('storefront.orders.payments.retry', ['publicUuid' => $order->public_uuid]), [
            'payment_idempotency_key' => $retryKey,
        ])->assertRedirect();

        $this->assertSame(2, Payment::query()->where('order_id', $order->getKey())->count());
        $this->simulateMockPaymentSuccess($order, 2);

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(1, Order::query()->where('idempotency_key', $idempotency)->count());
    }

    public function test_duplicate_mock_success_is_idempotent(): void
    {
        ['variant' => $variant] = $this->createPublishedSellableVariant('120.00');
        $cart = $this->createGuestCartWithItem($variant);
        $idempotency = hash('sha256', 'mock-idem-'.uniqid('', true));

        $this->withSession([GuestCartSession::SESSION_KEY => $cart->session_token])
            ->post(route('storefront.checkout.store'), [
                'idempotency_key' => $idempotency,
                'transaction_currency_code' => 'EGP',
                'content_locale' => 'en',
                'guest_email' => 'idem@example.test',
                'shipping_address' => $this->defaultAddresses()['shipping'],
                'billing_address' => $this->defaultAddresses()['billing'],
            ])->assertRedirect();

        $order = Order::query()->where('idempotency_key', $idempotency)->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->getKey())->firstOrFail();

        $successUrl = URL::temporarySignedRoute(
            'storefront.payments.mock.success',
            now()->addHour(),
            ['payment' => $payment->getKey()],
        );

        $this->post($successUrl)->assertRedirect();
        $this->post($successUrl)->assertRedirect();

        $this->assertSame(Payment::STATUS_CAPTURED, $payment->fresh()->status);
        $this->assertSame(1, Payment::query()->where('order_id', $order->getKey())->where('status', Payment::STATUS_CAPTURED)->count());
    }
}
