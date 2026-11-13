<?php

namespace Tests\Unit\Domains\Payments;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\Models\PaymentProviderEvent;
use App\Domains\Store\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);
    }

    private function createCustomerWithUser(): array
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Payer',
            'last_name' => 'Test',
        ]);

        return [$customer, $user];
    }

    private function createOrder(Customer $customer, ?User $user = null): Order
    {
        return Order::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-PAY-'.uniqid(),
            'customer_id' => $customer->getKey(),
            'user_id' => $user?->getKey(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'base_currency_code' => 'USD',
            'currency_code' => 'EGP',
            'content_locale' => 'en',
            'exchange_rate' => '48.50000000',
            'subtotal' => '1450.5000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'shipping_total' => '242.5000',
            'grand_total' => '1693.0000',
            'shipping_address' => ['line1' => '1 Main St', 'city' => 'Cairo', 'country_code' => 'EG'],
            'billing_address' => ['line1' => '1 Main St', 'city' => 'Cairo', 'country_code' => 'EG'],
            'idempotency_key' => hash('sha256', Str::random(32)),
        ]);
    }

    public function test_payment_persists_amount_currency_and_metadata(): void
    {
        [$customer, $user] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer, $user);

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '1693.0000',
            'currency_code' => 'EGP',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test_'.uniqid(),
            'idempotency_key' => hash('sha256', 'pay-attempt-1'),
            'metadata' => ['order_uuid' => $order->public_uuid],
        ]);
        $payment->refresh();

        $this->assertSame('1693.0000', (string) $payment->amount);
        $this->assertSame('EGP', $payment->currency_code);
        $this->assertIsArray($payment->metadata);
        $this->assertInstanceOf(Currency::class, $payment->currency);
        $this->assertSame('EGP', $payment->currency->code);
        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertTrue($order->payments()->whereKey($payment->getKey())->exists());
        $this->assertTrue(Currency::query()->where('code', 'EGP')->first()->payments()->whereKey($payment->getKey())->exists());
    }

    public function test_payment_retry_uses_incremented_attempt_number(): void
    {
        [$customer] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '1693.0000',
            'currency_code' => 'EGP',
            'status' => Payment::STATUS_FAILED,
            'idempotency_key' => hash('sha256', 'attempt-1'),
            'failed_at' => now(),
        ]);

        $retry = Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 2,
            'amount' => '1693.0000',
            'currency_code' => 'EGP',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => hash('sha256', 'attempt-2'),
        ]);

        $this->assertSame(2, $order->payments()->count());
        $this->assertTrue($order->payments()->whereKey($retry->getKey())->exists());
    }

    public function test_payment_status_scopes(): void
    {
        [$customer] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer);

        $pending = Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => hash('sha256', 'scope-pending'),
        ]);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 2,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_FAILED,
            'idempotency_key' => hash('sha256', 'scope-failed'),
        ]);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 3,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_CAPTURED,
            'idempotency_key' => hash('sha256', 'scope-captured'),
            'captured_at' => now(),
        ]);

        $this->assertSame(1, Payment::query()->pending()->whereKey($pending->getKey())->count());
        $this->assertSame(1, Payment::query()->failed()->count());
        $this->assertSame(1, Payment::query()->captured()->count());
    }

    public function test_payment_enforces_idempotency_key_unique(): void
    {
        [$customer] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer);
        $key = hash('sha256', 'duplicate-key');

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => $key,
        ]);

        $this->expectException(QueryException::class);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 2,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => $key,
        ]);
    }

    public function test_payment_provider_event_links_to_payment_and_allows_null_payment(): void
    {
        [$customer] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer);

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '1693.0000',
            'currency_code' => 'EGP',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_evt_'.uniqid(),
            'idempotency_key' => hash('sha256', 'evt-pay'),
        ]);

        $linked = PaymentProviderEvent::query()->create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_'.uniqid(),
            'event_type' => 'payment_intent.succeeded',
            'payment_id' => $payment->getKey(),
            'payload' => ['id' => 'evt_test'],
            'processing_result' => PaymentProviderEvent::PROCESSING_RESULT_SUCCESS,
            'processed_at' => now(),
            'created_at' => now(),
        ]);

        $orphan = PaymentProviderEvent::query()->create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_orphan_'.uniqid(),
            'event_type' => 'unknown.event',
            'payment_id' => null,
            'payload' => ['unmatched' => true],
            'created_at' => now(),
        ]);

        $this->assertNull($orphan->updated_at);
        $this->assertInstanceOf(Payment::class, $linked->payment);
        $this->assertTrue($payment->providerEvents()->whereKey($linked->getKey())->exists());
        $this->assertNull($orphan->payment);
        $this->assertIsArray($linked->payload);
    }

    public function test_payment_provider_event_enforces_provider_event_unique(): void
    {
        $provider = 'stripe';
        $eventId = 'evt_dup_'.uniqid();

        PaymentProviderEvent::query()->create([
            'provider' => $provider,
            'provider_event_id' => $eventId,
            'event_type' => 'charge.succeeded',
            'payload' => ['id' => $eventId],
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        PaymentProviderEvent::query()->create([
            'provider' => $provider,
            'provider_event_id' => $eventId,
            'event_type' => 'charge.succeeded',
            'payload' => ['id' => $eventId],
            'created_at' => now(),
        ]);
    }

    public function test_payment_nullable_provider_fields_before_selection(): void
    {
        [$customer] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer);

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '1693.0000',
            'currency_code' => 'EGP',
            'status' => Payment::STATUS_PENDING,
            'provider' => null,
            'provider_payment_id' => null,
            'provider_checkout_url' => null,
            'idempotency_key' => hash('sha256', 'no-provider-yet'),
        ]);

        $payment->refresh();

        $this->assertNull($payment->provider);
        $this->assertNull($payment->provider_payment_id);
        $this->assertNull($payment->provider_checkout_url);
    }

    public function test_payment_enforces_order_attempt_unique(): void
    {
        [$customer] = $this->createCustomerWithUser();
        $order = $this->createOrder($customer);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => hash('sha256', 'uniq-a'),
        ]);

        $this->expectException(QueryException::class);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => hash('sha256', 'uniq-b'),
        ]);
    }
}
