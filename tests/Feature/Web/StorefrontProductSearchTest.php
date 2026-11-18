<?php

namespace Tests\Feature\Web;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductSearchTest extends TestCase
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

    public function test_search_finds_product_by_localized_name(): void
    {
        $this->createSellableProduct('UniqueAlpha Jacket', 'alpha-jacket');
        $this->createSellableProduct('Other Beta Coat', 'beta-coat');

        $this->get(route('storefront.shop.index', ['q' => 'UniqueAlpha']))
            ->assertOk()
            ->assertSee('UniqueAlpha Jacket', false)
            ->assertDontSee('Other Beta Coat', false);
    }

    public function test_search_combined_with_category_filter(): void
    {
        $category = Category::query()->create([
            'name' => 'Outerwear',
            'slug' => 'outerwear',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $match = $this->createSellableProduct('Searchable Parka', 'search-parka');
        $match->categories()->attach($category->getKey());

        $other = $this->createSellableProduct('Searchable Tee', 'search-tee');

        $this->get(route('storefront.shop.index', ['q' => 'Searchable', 'category' => 'outerwear']))
            ->assertOk()
            ->assertSee('Searchable Parka', false)
            ->assertDontSee('Searchable Tee', false);
    }

    public function test_draft_product_not_in_search_results(): void
    {
        $product = $this->createSellableProduct('Hidden Draft Name', 'draft-hidden');
        $product->update(['status' => Product::STATUS_DRAFT]);

        $this->get(route('storefront.shop.index', ['q' => 'Hidden Draft']))
            ->assertOk()
            ->assertDontSee('Hidden Draft Name', false)
            ->assertSee(__('storefront.shop.empty_title', [], 'en'), false);
    }

    public function test_empty_search_results_message(): void
    {
        $this->createSellableProduct('Visible Only', 'visible-only');

        $this->get(route('storefront.shop.index', ['q' => 'zzznomatch999']))
            ->assertOk()
            ->assertSee(__('storefront.shop.empty_title', [], 'en'), false);
    }

    private function createSellableProduct(string $enName, string $slug): Product
    {
        $product = Product::query()->create([
            'name' => $enName,
            'slug' => $slug,
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => $enName,
            'short_description' => 'Short '.$enName,
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => 'عربي '.$enName,
        ]);

        $size = Size::query()->create(['code' => 'S-'.$slug, 'label' => 'S']);
        $color = Color::query()->create(['name' => 'Red', 'slug' => 'red-'.$slug]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SKU-'.$slug,
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '50.00',
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        InventoryLevel::query()->create([
            'product_variant_id' => $variant->getKey(),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        return $product;
    }
}
