<?php

namespace Tests\Feature\Console;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryReservation;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireInventoryReservationsCommandTest extends TestCase
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

    public function test_command_expires_reservations(): void
    {
        $cart = Cart::query()->create([
            'session_token' => 'cmd-'.uniqid(),
            'base_currency_code' => 'USD',
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);

        $product = Product::query()->create([
            'name' => 'Cmd Product',
            'slug' => 'cmd-product-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);
        $size = Size::query()->create(['code' => 'C', 'label' => 'Cmd']);
        $color = Color::query()->create(['name' => 'Cmd', 'slug' => 'cmd-'.uniqid()]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CMD-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '15.00',
        ]);

        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 8,
            'quantity_reserved' => 2,
        ]);

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 2,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'cart_id' => $cart->getKey(),
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('inventory:expire-reservations', ['--limit' => 10])
            ->assertSuccessful();

        $this->assertSame(InventoryReservation::STATUS_EXPIRED, $reservation->fresh()->status);
    }
}
