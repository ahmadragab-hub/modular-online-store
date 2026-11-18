<?php

namespace Tests\Feature\Web;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
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

    public function test_customer_sees_only_own_orders(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Test',
            'last_name' => 'Buyer',
        ]);

        $own = $this->createOrderForCustomer($customer);
        $otherCustomer = Customer::query()->create([
            'email' => 'other@example.test',
            'first_name' => 'Other',
            'last_name' => 'User',
        ]);
        $foreign = $this->createOrderForCustomer($otherCustomer);

        $this->actingAs($user)
            ->get(route('storefront.account.orders.index'))
            ->assertOk()
            ->assertSee($own->order_number, false)
            ->assertDontSee($foreign->order_number, false);
    }

    public function test_other_customers_order_detail_returns_not_found(): void
    {
        $user = User::factory()->create();
        Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Test',
            'last_name' => 'Buyer',
        ]);

        $foreign = $this->createOrderForCustomer(Customer::query()->create([
            'email' => 'foreign@example.test',
            'first_name' => 'Foreign',
            'last_name' => 'Buyer',
        ]));

        $this->actingAs($user)
            ->get(route('storefront.account.orders.show', ['publicUuid' => $foreign->public_uuid]))
            ->assertNotFound();
    }

    public function test_guest_cannot_access_order_history(): void
    {
        $this->get(route('storefront.account.orders.index'))->assertRedirect(route('storefront.login.create'));
    }

    private function createOrderForCustomer(Customer $customer): Order
    {
        $order = Order::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $customer->getKey(),
            'user_id' => $customer->user_id,
            'guest_email' => $customer->email,
            'status' => Order::STATUS_PROCESSING,
            'base_currency_code' => 'EGP',
            'currency_code' => 'EGP',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '100.0000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'shipping_total' => '0.0000',
            'grand_total' => '100.0000',
            'shipping_address' => ['line1' => '1 St', 'city' => 'Cairo', 'country_code' => 'EG'],
            'billing_address' => ['line1' => '1 St', 'city' => 'Cairo', 'country_code' => 'EG'],
            'idempotency_key' => hash('sha256', 'hist-'.uniqid('', true)),
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_variant_id' => null,
            'sku' => 'SKU-HIST',
            'product_name' => 'History Product',
            'variant_label' => 'M / Blue',
            'quantity' => 1,
            'unit_price_base_usd' => '100.00',
            'unit_price' => '100.0000',
            'line_subtotal' => '100.0000',
        ]);

        return $order;
    }
}
