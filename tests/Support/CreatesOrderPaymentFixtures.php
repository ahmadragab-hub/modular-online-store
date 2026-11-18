<?php

namespace Tests\Support;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Payments\Models\Payment;

trait CreatesOrderPaymentFixtures
{
    /**
     * @param  array<int, array{variant: ProductVariant, quantity: int, reservation?: InventoryReservation}>  $lines
     */
    protected function createUnpaidOrderWithLines(
        string $orderStatus = Order::STATUS_PENDING_PAYMENT,
        array $lines = [],
    ): Order {
        $order = Order::query()->create([
            'public_uuid' => (string) str()->uuid(),
            'order_number' => 'ORD-'.uniqid(),
            'status' => $orderStatus,
            'currency_code' => 'USD',
            'content_locale' => 'en',
            'exchange_rate' => '1.00000000',
            'subtotal' => '20.0000',
            'grand_total' => '20.0000',
            'shipping_address' => $this->minimalAddress(),
            'billing_address' => $this->minimalAddress(),
            'idempotency_key' => hash('sha256', uniqid('ord-', true)),
            'placed_at' => now(),
        ]);

        $cart = Cart::query()->create([
            'session_token' => 'cart-'.uniqid(),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_CONVERTED,
        ]);

        foreach ($lines as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $quantity = (int) $line['quantity'];

            OrderItem::query()->create([
                'order_id' => $order->getKey(),
                'product_variant_id' => $variant->getKey(),
                'product_id' => $variant->product_id,
                'product_name' => 'Line',
                'sku' => $variant->sku,
                'variant_label' => 'M',
                'quantity' => $quantity,
                'unit_price_base_usd' => '10.00',
                'unit_price' => '10.0000',
                'line_subtotal' => bcmul('10.0000', (string) $quantity, 4),
                'tax_amount' => '0.0000',
                'created_at' => now(),
            ]);

            InventoryLevel::query()->firstOrCreate(
                ['product_variant_id' => $variant->getKey()],
                ['quantity_on_hand' => 20, 'quantity_reserved' => 0],
            );

            $level = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->first();
            $level->quantity_reserved += $quantity;
            $level->save();

            $reservation = $line['reservation'] ?? InventoryReservation::query()->create([
                'product_variant_id' => $variant->getKey(),
                'quantity' => $quantity,
                'status' => InventoryReservation::STATUS_ACTIVE,
                'cart_id' => $cart->getKey(),
                'order_id' => $order->getKey(),
                'expires_at' => now()->addHour(),
            ]);
        }

        return $order->fresh();
    }

    protected function createPaymentForOrder(Order $order, string $status = Payment::STATUS_PENDING): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->getKey(),
            'attempt_number' => 1,
            'amount' => $order->grand_total,
            'currency_code' => $order->currency_code,
            'status' => $status,
            'idempotency_key' => hash('sha256', uniqid('pay-', true)),
        ]);
    }

    protected function createCatalogVariant(string $sku = 'SKU'): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'P',
            'slug' => 'p-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);
        $size = Size::query()->create(['code' => 'SZ-'.uniqid(), 'label' => 'T']);
        $color = Color::query()->create(['name' => 'Gray', 'slug' => 'g-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => $sku.'-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '10.00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function minimalAddress(): array
    {
        return [
            'line1' => '1',
            'city' => 'C',
            'country_code' => 'EG',
            'first_name' => 'A',
            'last_name' => 'B',
        ];
    }
}
