<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\AdjustStockAction;
use App\Domains\Inventory\Exceptions\StockAdjustmentException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustStockActionTest extends TestCase
{
    use RefreshDatabase;

    private AdjustStockAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(AdjustStockAction::class);
    }

    public function test_rejects_adjustment_below_reserved(): void
    {
        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 8,
        ]);

        $admin = User::factory()->admin()->create();

        $this->expectException(StockAdjustmentException::class);
        $this->action->execute((int) $variant->getKey(), -5, (int) $admin->getKey(), 'test');
    }

    public function test_records_movement_on_success(): void
    {
        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $admin = User::factory()->admin()->create();
        $this->action->execute((int) $variant->getKey(), 3, (int) $admin->getKey(), 'restock');

        $this->assertSame(13, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->value('quantity_on_hand'));
        $this->assertSame(1, InventoryMovement::query()->where('product_variant_id', $variant->getKey())->count());
    }

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create(['name' => 'P', 'slug' => 'p-'.uniqid(), 'status' => Product::STATUS_DRAFT]);
        $size = Size::query()->create(['code' => 'ADJ', 'label' => 'Adj']);
        $color = Color::query()->create(['name' => 'Blue', 'slug' => 'blue-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'ADJ-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '12.00',
        ]);
    }
}
