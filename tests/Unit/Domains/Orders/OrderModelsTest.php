<?php

namespace Tests\Unit\Domains\Orders;

use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderModelsTest extends TestCase
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

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Order Product',
            'slug' => 'order-product-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);
        $size = Size::query()->create(['code' => 'M', 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Navy', 'slug' => 'navy-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'ORD-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '99.00',
        ]);
    }

    private function createOrder(Customer $customer, User $user): Order
    {
        return Order::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
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

    public function test_order_persists_frozen_currency_and_amounts(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Buyer',
            'last_name' => 'One',
        ]);

        $order = $this->createOrder($customer, $user);
        $order->refresh();

        $this->assertSame('USD', $order->baseCurrency->code);
        $this->assertSame('EGP', $order->transactionCurrency->code);
        $this->assertSame('en', $order->contentLocale->code);
        $this->assertSame('48.50000000', (string) $order->exchange_rate);
        $this->assertSame('1693.0000', (string) $order->grand_total);
        $this->assertIsArray($order->shipping_address);
        $this->assertTrue($customer->orders()->whereKey($order->getKey())->exists());
        $this->assertTrue($user->orders()->whereKey($order->getKey())->exists());
    }

    public function test_order_item_historical_snapshots(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Buyer',
            'last_name' => 'Two',
        ]);
        $order = $this->createOrder($customer, $user);
        $variant = $this->createVariant();

        $item = OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_variant_id' => $variant->getKey(),
            'product_id' => $variant->product_id,
            'product_name' => 'Snapshot Title',
            'sku' => $variant->sku,
            'variant_label' => 'M / Navy',
            'quantity' => 2,
            'unit_price_base_usd' => '99.00',
            'unit_price' => '4801.5000',
            'line_subtotal' => '9603.0000',
            'created_at' => now(),
        ]);
        $item->refresh();

        $this->assertNull($item->updated_at);
        $this->assertSame('99.00', (string) $item->unit_price_base_usd);
        $this->assertSame('4801.5000', (string) $item->unit_price);
        $this->assertInstanceOf(ProductVariant::class, $item->productVariant);
        $this->assertTrue($order->items()->whereKey($item->getKey())->exists());
        $this->assertTrue($variant->orderItems()->whereKey($item->getKey())->exists());
    }

    public function test_status_history_and_reservation_order_link(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Buyer',
            'last_name' => 'Three',
        ]);
        $order = $this->createOrder($customer, $user);
        $variant = $this->createVariant();

        $history = OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => null,
            'to_status' => Order::STATUS_PENDING_PAYMENT,
            'actor_user_id' => $user->getKey(),
            'created_at' => now(),
        ]);

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'order_id' => $order->getKey(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertInstanceOf(User::class, $history->actorUser);
        $this->assertInstanceOf(Order::class, $reservation->order);
        $this->assertTrue($order->statusHistories()->whereKey($history->getKey())->exists());
        $this->assertTrue($order->reservations()->whereKey($reservation->getKey())->exists());
    }

    public function test_order_status_scopes(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Buyer',
            'last_name' => 'Four',
        ]);

        $pending = $this->createOrder($customer, $user);
        Order::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-FAIL-'.uniqid(),
            'customer_id' => $customer->getKey(),
            'status' => Order::STATUS_PAYMENT_FAILED,
            'base_currency_code' => 'USD',
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'tax_total' => '0.0000',
            'shipping_total' => '0.0000',
            'grand_total' => '10.0000',
            'shipping_address' => ['line1' => 'x'],
            'billing_address' => ['line1' => 'x'],
        ]);

        $this->assertSame(1, Order::query()->pendingPayment()->whereKey($pending->getKey())->count());
        $this->assertSame(1, Order::query()->paymentFailed()->count());
    }
}
