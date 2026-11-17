<?php

namespace Tests\Unit\Domains\Identity;

use App\Domains\Customers\Exceptions\CustomerRegistrationException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Actions\RegisterCustomerAction;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Models\Order;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterCustomerActionTest extends TestCase
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

    public function test_registers_new_user_and_customer(): void
    {
        $user = app(RegisterCustomerAction::class)->execute(
            'Ada@EXAMPLE.test',
            'password123',
            'Ada',
            'Lovelace',
        );

        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertTrue($user->isActive());
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.test', $user->email);

        $customer = Customer::query()->where('user_id', $user->getKey())->first();
        $this->assertNotNull($customer);
        $this->assertSame('ada@example.test', $customer->email);
    }

    public function test_links_guest_customer_when_stored_email_differs_by_case(): void
    {
        $guest = Customer::query()->create([
            'email' => 'MixedCase@Example.test',
            'first_name' => 'G',
            'last_name' => 'Guest',
        ]);

        $user = app(RegisterCustomerAction::class)->execute(
            'mixedcase@example.test',
            'password123',
            'Reg',
            'User',
        );

        $guest->refresh();
        $this->assertSame($user->getKey(), $guest->user_id);
        $this->assertSame('mixedcase@example.test', $user->email);
        $this->assertSame('mixedcase@example.test', $guest->email);
    }

    public function test_links_existing_guest_customer_preserving_id(): void
    {
        $guest = Customer::query()->create([
            'email' => 'guest@example.test',
            'first_name' => 'G',
            'last_name' => 'Guest',
        ]);

        $order = Order::query()->create([
            'public_uuid' => (string) str()->uuid(),
            'order_number' => 'ORD-TEST-1',
            'customer_id' => $guest->getKey(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'base_currency_code' => 'USD',
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '10.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'grand_total' => '10.00',
            'shipping_address' => ['line1' => '1 St'],
            'billing_address' => ['line1' => '1 St'],
            'idempotency_key' => hash('sha256', 'order-link-test'),
            'placed_at' => now(),
        ]);

        $user = app(RegisterCustomerAction::class)->execute(
            'guest@example.test',
            'password123',
            'Reg',
            'User',
        );

        $guest->refresh();
        $this->assertSame($guest->getKey(), $guest->getKey());
        $this->assertSame($user->getKey(), $guest->user_id);
        $this->assertSame('Reg', $guest->first_name);
        $this->assertSame(1, Customer::query()->where('email', 'guest@example.test')->count());
        $this->assertSame($guest->getKey(), $order->fresh()->customer_id);
    }

    public function test_rejects_existing_user_email(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->expectException(CustomerRegistrationException::class);
        app(RegisterCustomerAction::class)->execute(
            'taken@example.test',
            'password123',
            'A',
            'B',
        );
    }

    public function test_rejects_customer_already_linked(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => 'registered@example.test',
            'first_name' => 'R',
            'last_name' => 'U',
        ]);

        $this->expectException(CustomerRegistrationException::class);
        app(RegisterCustomerAction::class)->execute(
            'registered@example.test',
            'password123',
            'A',
            'B',
        );
    }
}
