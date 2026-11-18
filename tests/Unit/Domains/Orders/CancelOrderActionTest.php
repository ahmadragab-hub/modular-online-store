<?php

namespace Tests\Unit\Domains\Orders;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Actions\CancelOrderAction;
use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelOrderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_releases_active_reservation(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $order = Order::query()->create([
            'public_uuid' => (string) str()->uuid(),
            'order_number' => 'ORD-CAN',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '10.0000',
            'grand_total' => '10.0000',
            'shipping_address' => ['line1' => '1', 'city' => 'C', 'country_code' => 'EG', 'first_name' => 'A', 'last_name' => 'B'],
            'billing_address' => ['line1' => '1', 'city' => 'C', 'country_code' => 'EG', 'first_name' => 'A', 'last_name' => 'B'],
            'idempotency_key' => hash('sha256', 'cancel-order'),
            'placed_at' => now(),
        ]);

        Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => Payment::STATUS_PENDING,
            'idempotency_key' => hash('sha256', 'pay-cancel'),
        ]);

        $cart = Cart::query()->create([
            'session_token' => 'c',
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_CONVERTED,
        ]);

        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 1,
        ]);

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'order_id' => $order->getKey(),
            'expires_at' => now()->addHour(),
        ]);

        $admin = User::factory()->admin()->create();
        $action = $this->app->make(CancelOrderAction::class);
        $action->execute((int) $order->getKey(), (int) $admin->getKey(), 'admin cancel');

        $reservation->refresh();
        $this->assertSame(InventoryReservation::STATUS_RELEASED, $reservation->status);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(0, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_reserved'));
    }

    public function test_cancel_from_processing_is_forbidden(): void
    {
        $this->seed([CurrencySeeder::class, StoreLocaleSeeder::class]);

        $order = Order::query()->create([
            'public_uuid' => (string) str()->uuid(),
            'order_number' => 'ORD-PROC',
            'status' => Order::STATUS_PROCESSING,
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '10.0000',
            'grand_total' => '10.0000',
            'shipping_address' => ['line1' => '1', 'city' => 'C', 'country_code' => 'EG', 'first_name' => 'A', 'last_name' => 'B'],
            'billing_address' => ['line1' => '1', 'city' => 'C', 'country_code' => 'EG', 'first_name' => 'A', 'last_name' => 'B'],
            'idempotency_key' => hash('sha256', 'cancel-proc'),
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        $admin = User::factory()->admin()->create();
        $action = $this->app->make(CancelOrderAction::class);

        $this->expectException(OrderTransitionException::class);
        $action->execute((int) $order->getKey(), (int) $admin->getKey());
    }

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create(['name' => 'P', 'slug' => 'p-'.uniqid(), 'status' => Product::STATUS_DRAFT]);
        $size = Size::query()->create(['code' => 'C', 'label' => 'C']);
        $color = Color::query()->create(['name' => 'Gray', 'slug' => 'gray-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CAN-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '10.00',
        ]);
    }
}
