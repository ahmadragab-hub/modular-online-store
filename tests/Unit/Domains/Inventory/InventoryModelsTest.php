<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryReservation;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryModelsTest extends TestCase
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
            'name' => 'Stock Item',
            'slug' => 'stock-item-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'M', 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Navy', 'slug' => 'navy-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SKU-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '25.00',
        ]);
    }

    public function test_inventory_level_one_per_variant_and_relationship(): void
    {
        $variant = $this->createVariant();

        $level = InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
        ]);
        $level->refresh();

        $this->assertSame('inventory_levels', $level->getTable());
        $this->assertNull($level->created_at);
        $this->assertSame(10, $level->quantity_on_hand);
        $this->assertInstanceOf(ProductVariant::class, $level->productVariant);
        $this->assertSame($variant->sku, $level->productVariant->sku);

        $this->expectException(QueryException::class);
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
        ]);
    }

    public function test_inventory_movement_immutable_audit_row(): void
    {
        $variant = $this->createVariant();
        $admin = User::factory()->admin()->create();

        $movement = InventoryMovement::query()->create([
            'product_variant_id' => $variant->getKey(),
            'type' => InventoryMovement::TYPE_ADJUSTMENT_IN,
            'quantity_delta' => 10,
            'quantity_on_hand_after' => 10,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'created_by_user_id' => $admin->getKey(),
            'created_at' => now(),
        ]);

        $this->assertNull($movement->updated_at);
        $this->assertInstanceOf(User::class, $movement->createdByUser);
        $this->assertTrue($variant->getKey() === $movement->productVariant->getKey());
    }

    public function test_inventory_reservation_persistence_and_scopes(): void
    {
        $variant = $this->createVariant();

        $reservation = InventoryReservation::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity' => 3,
            'status' => InventoryReservation::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(15),
        ]);
        $reservation->refresh();

        $this->assertInstanceOf(ProductVariant::class, $reservation->productVariant);
        $this->assertNull($reservation->cart_id);
        $this->assertNull($reservation->order_id);

        $this->assertTrue(
            InventoryReservation::query()->active()->notExpired()->whereKey($reservation->getKey())->exists()
        );
    }
}
