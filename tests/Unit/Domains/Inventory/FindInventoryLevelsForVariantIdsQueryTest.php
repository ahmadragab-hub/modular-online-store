<?php

namespace Tests\Unit\Domains\Inventory;

use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Exceptions\MissingInventoryLevelException;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Queries\FindInventoryLevelsForVariantIdsQuery;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FindInventoryLevelsForVariantIdsQueryTest extends TestCase
{
    use RefreshDatabase;

    private FindInventoryLevelsForVariantIdsQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
        ]);

        $this->query = new FindInventoryLevelsForVariantIdsQuery;
    }

    private function createVariant(string $suffix = ''): ProductVariant
    {
        $product = Product::query()->create([
            'name' => 'Stock Item '.$suffix,
            'slug' => 'stock-item-'.$suffix.'-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $size = Size::query()->create(['code' => 'M-'.$suffix, 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Navy', 'slug' => 'navy-'.$suffix.'-'.uniqid()]);

        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SKU-'.$suffix.'-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '25.00',
        ]);
    }

    private function createLevel(ProductVariant $variant, int $onHand, int $reserved = 0): InventoryLevel
    {
        return InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
        ]);
    }

    public function test_returns_empty_collection_when_no_variant_ids_given(): void
    {
        $levels = $this->query->find([]);

        $this->assertCount(0, $levels);
    }

    public function test_returns_levels_in_ascending_product_variant_id_order(): void
    {
        $variantA = $this->createVariant('a');
        $variantB = $this->createVariant('b');
        $variantC = $this->createVariant('c');

        $this->createLevel($variantC, 3);
        $this->createLevel($variantA, 10);
        $this->createLevel($variantB, 5);

        $levels = $this->query->find([
            $variantC->getKey(),
            $variantA->getKey(),
            $variantB->getKey(),
        ]);

        $this->assertCount(3, $levels);
        $this->assertSame(
            [
                $variantA->getKey(),
                $variantB->getKey(),
                $variantC->getKey(),
            ],
            $levels->pluck('product_variant_id')->all(),
        );
    }

    public function test_deduplicates_variant_ids_before_loading(): void
    {
        $variant = $this->createVariant('dup');
        $this->createLevel($variant, 7);

        $levels = $this->query->find([
            $variant->getKey(),
            $variant->getKey(),
        ]);

        $this->assertCount(1, $levels);
        $this->assertSame(7, $levels->first()->quantity_on_hand);
    }

    public function test_throws_when_inventory_level_is_missing(): void
    {
        $variantWithLevel = $this->createVariant('with');
        $variantWithoutLevel = $this->createVariant('without');
        $this->createLevel($variantWithLevel, 4);

        try {
            $this->query->find([
                $variantWithLevel->getKey(),
                $variantWithoutLevel->getKey(),
            ]);
            $this->fail('Expected MissingInventoryLevelException was not thrown.');
        } catch (MissingInventoryLevelException $exception) {
            $this->assertSame([$variantWithoutLevel->getKey()], $exception->missingProductVariantIds);
        }
    }

    public function test_loads_multiple_levels_in_one_query(): void
    {
        $variantOne = $this->createVariant('one');
        $variantTwo = $this->createVariant('two');
        $this->createLevel($variantOne, 12, 1);
        $this->createLevel($variantTwo, 8, 3);

        DB::enableQueryLog();

        $levels = $this->query->find([
            $variantTwo->getKey(),
            $variantOne->getKey(),
        ]);

        $inventoryQueries = collect(DB::getQueryLog())
            ->filter(fn (array $entry): bool => str_contains($entry['query'], 'inventory_levels'))
            ->count();

        $this->assertSame(1, $inventoryQueries);
        $this->assertSame(12, $levels->firstWhere('product_variant_id', $variantOne->getKey())->quantity_on_hand);
        $this->assertSame(3, $levels->firstWhere('product_variant_id', $variantTwo->getKey())->quantity_reserved);
    }

    public function test_lock_for_update_succeeds_inside_active_transaction(): void
    {
        $variant = $this->createVariant('lock');
        $this->createLevel($variant, 6);

        DB::transaction(function () use ($variant): void {
            $levels = $this->query->find([$variant->getKey()], lockForUpdate: true);

            $this->assertCount(1, $levels);
            $this->assertSame(6, $levels->first()->quantity_on_hand);
        });

        // SQLite testing does not enforce InnoDB row locks; MySQL concurrency tests belong in Phase 2e.
        $this->assertSame('sqlite', config('database.default'));
    }
}
