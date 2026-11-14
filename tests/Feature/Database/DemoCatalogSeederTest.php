<?php

namespace Tests\Feature\Database;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Queries\GetProductBySlugQuery;
use App\Domains\Catalog\Queries\ListPublishedProductsQuery;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Store\Models\CurrencyExchangeRate;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);
    }

    public function test_demo_catalog_seeder_runs_and_is_idempotent(): void
    {
        $ratesBefore = CurrencyExchangeRate::query()->count();

        $this->seed(DemoCatalogSeeder::class);
        $firstProducts = Product::query()->whereIn('slug', $this->demoSlugs())->count();
        $firstVariants = ProductVariant::query()->where('sku', 'like', 'DEMO-%')->count();

        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame($firstProducts, Product::query()->whereIn('slug', $this->demoSlugs())->count());
        $this->assertSame($firstVariants, ProductVariant::query()->where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame($ratesBefore, CurrencyExchangeRate::query()->count());
    }

    public function test_repeat_seeder_does_not_reset_existing_inventory(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'DEMO-OXF-M-WHT')->firstOrFail();
        $level = InventoryLevel::query()->where('product_variant_id', $variant->getKey())->firstOrFail();

        $level->update([
            'quantity_on_hand' => 77,
            'quantity_reserved' => 3,
        ]);

        $this->seed(DemoCatalogSeeder::class);

        $level->refresh();
        $this->assertSame(77, $level->quantity_on_hand);
        $this->assertSame(3, $level->quantity_reserved);
        $this->assertSame(1, InventoryLevel::query()->where('product_variant_id', $variant->getKey())->count());
    }

    public function test_seeded_products_are_storefront_eligible(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $listQuery = $this->app->make(ListPublishedProductsQuery::class);
        $products = $listQuery->paginate('en', 50);

        $this->assertGreaterThanOrEqual(6, $products->total());

        $detailQuery = $this->app->make(GetProductBySlugQuery::class);
        $product = $detailQuery->find('classic-oxford-shirt', 'en');
        $this->assertNotNull($product);
        $this->assertSame(Product::STATUS_PUBLISHED, $product->status);
        $this->assertTrue($product->variants()->where('status', ProductVariant::STATUS_ACTIVE)->exists());
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'ar',
        ]);
        $this->assertGreaterThan(0, InventoryLevel::query()->whereIn(
            'product_variant_id',
            $product->variants()->pluck('id'),
        )->sum('quantity_on_hand'));
    }

    /**
     * @return list<string>
     */
    private function demoSlugs(): array
    {
        return [
            'classic-oxford-shirt',
            'linen-relaxed-trousers',
            'midi-satin-slip-dress',
            'wool-blend-overcoat',
            'merino-rib-knit-sweater',
            'leather-crossbody-bag',
        ];
    }
}
