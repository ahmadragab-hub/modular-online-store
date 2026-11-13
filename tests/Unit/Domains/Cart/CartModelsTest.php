<?php

namespace Tests\Unit\Domains\Cart;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryReservation;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartModelsTest extends TestCase
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
            'name' => 'Cart Product',
            'slug' => 'cart-product-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);
        $size = Size::query()->create(['code' => 'L', 'label' => 'Large']);
        $color = Color::query()->create(['name' => 'Red', 'slug' => 'red-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CART-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '150.00',
        ]);
    }

    public function test_guest_cart_persistence_and_currencies(): void
    {
        $cart = Cart::query()->create([
            'session_token' => hash('sha256', Str::random(32)),
            'display_currency_code' => 'EGP',
            'status' => Cart::STATUS_ACTIVE,
        ]);
        $cart->refresh();

        $this->assertNull($cart->customer_id);
        $this->assertSame('USD', $cart->base_currency_code);
        $this->assertSame('EGP', $cart->displayCurrency->code);
    }

    public function test_registered_customer_cart_and_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => 'Cart',
            'last_name' => 'User',
        ]);
        $variant = $this->createVariant();

        $cart = Cart::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $user->getKey(),
            'display_currency_code' => 'USD',
        ]);

        $item = CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'unit_price_base' => '150.00',
            'product_name' => 'Cart Product',
            'sku' => $variant->sku,
            'variant_label' => 'L / Red',
        ]);
        $item->refresh();

        $this->assertInstanceOf(Customer::class, $cart->customer);
        $this->assertTrue($customer->carts()->whereKey($cart->getKey())->exists());
        $this->assertSame(2, $item->quantity);
        $this->assertSame('150.00', (string) $item->unit_price_base);
        $this->assertInstanceOf(ProductVariant::class, $item->productVariant);
        $this->assertTrue($cart->items()->whereKey($item->getKey())->exists());
        $this->assertTrue($variant->cartItems()->whereKey($item->getKey())->exists());
    }

    public function test_unique_variant_per_cart(): void
    {
        $cart = Cart::query()->create([
            'session_token' => hash('sha256', Str::random(32)),
            'display_currency_code' => 'USD',
        ]);
        $variant = $this->createVariant();

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '10.00',
            'product_name' => 'A',
            'sku' => $variant->sku,
            'variant_label' => 'L / Red',
        ]);

        $this->expectException(QueryException::class);

        CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price_base' => '10.00',
            'product_name' => 'A',
            'sku' => $variant->sku,
            'variant_label' => 'L / Red',
        ]);
    }

    public function test_inventory_reservation_cart_relationship(): void
    {
        $cart = Cart::query()->create([
            'session_token' => hash('sha256', Str::random(32)),
            'display_currency_code' => 'USD',
        ]);
        $variant = $this->createVariant();

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 1,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertInstanceOf(Cart::class, $reservation->cart);
        $this->assertTrue($cart->reservations()->whereKey($reservation->getKey())->exists());
    }
}
