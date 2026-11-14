<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Queries\GetAvailableQuantityForVariantsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GetAvailableQuantityForVariantsQueryTest extends TestCase
{
    use RefreshDatabase;

    private GetAvailableQuantityForVariantsQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query = $this->app->make(GetAvailableQuantityForVariantsQuery::class);
    }

    private function createVariant(): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Stock Product',
            'slug' => 'stock-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'ST-'.uniqid(), 'label' => 'Stock']);
        $color = Color::query()->create(['name' => 'Stock', 'slug' => 'stock-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'ST-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '25.00',
        ]);
    }

    public function test_calculates_available_quantity_with_reserved_stock(): void
    {
        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 3,
        ]);

        $map = $this->query->quantitiesForVariantIds([$variant->getKey()]);

        $this->assertSame([$variant->getKey() => 7], $map);
    }

    public function test_returns_zero_when_reserved_equals_on_hand(): void
    {
        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 5,
        ]);

        $map = $this->query->quantitiesForVariantIds([$variant->getKey()]);

        $this->assertSame(0, $map[$variant->getKey()]);
    }

    public function test_missing_level_returns_zero(): void
    {
        $variant = $this->createVariant();

        $map = $this->query->quantitiesForVariantIds([$variant->getKey()]);

        $this->assertSame(0, $map[$variant->getKey()]);
    }

    public function test_batch_fetches_all_ids_in_one_query(): void
    {
        $variantA = $this->createVariant();
        $variantB = $this->createVariant();

        InventoryLevel::query()->create([
            'product_variant_id' => $variantA->getKey(),
            'quantity_on_hand' => 4,
            'quantity_reserved' => 1,
        ]);

        InventoryLevel::query()->create([
            'product_variant_id' => $variantB->getKey(),
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $map = $this->query->quantitiesForVariantIds([
            $variantB->getKey(),
            $variantA->getKey(),
            $variantA->getKey(),
        ]);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queryCount);
        $this->assertSame(3, $map[$variantA->getKey()]);
        $this->assertSame(2, $map[$variantB->getKey()]);
    }

    public function test_empty_input_returns_empty_map(): void
    {
        $this->assertSame([], $this->query->quantitiesForVariantIds([]));
    }

    public function test_clamps_negative_availability_to_zero(): void
    {
        $variant = $this->createVariant();
        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 2,
            'quantity_reserved' => 5,
        ]);

        $map = $this->query->quantitiesForVariantIds([$variant->getKey()]);

        $this->assertSame(0, $map[$variant->getKey()]);
    }
}
