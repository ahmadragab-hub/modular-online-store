<?php

namespace Tests\Unit\Domains\Catalog;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Catalog\Queries\GetProductBySlugQuery;
use App\Domains\Catalog\Queries\ListActiveCategoriesQuery;
use App\Domains\Catalog\Queries\ListPublishedProductsQuery;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogStorefrontQueriesTest extends TestCase
{
    use RefreshDatabase;

    private ListPublishedProductsQuery $listQuery;

    private GetProductBySlugQuery $bySlugQuery;

    private ListActiveCategoriesQuery $categoriesQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StoreLocaleSeeder::class);

        $this->listQuery = $this->app->make(ListPublishedProductsQuery::class);
        $this->bySlugQuery = $this->app->make(GetProductBySlugQuery::class);
        $this->categoriesQuery = $this->app->make(ListActiveCategoriesQuery::class);
    }

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function createStorefrontProduct(
        string $slug,
        string $enName = 'Product EN',
        string $arName = 'منتج',
        string $variantStatus = ProductVariant::STATUS_ACTIVE,
        string $productStatus = Product::STATUS_PUBLISHED,
    ): array {
        $product = Product::query()->create([
            'name' => $enName,
            'slug' => $slug,
            'status' => $productStatus,
            'published_at' => now(),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => $enName,
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => $arName,
        ]);

        $size = Size::query()->create(['code' => 'S-'.$slug, 'label' => 'Small']);
        $color = Color::query()->create(['name' => 'Teal', 'slug' => 'teal-'.$slug]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SKU-'.$slug,
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '80.00',
            'status' => $variantStatus,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'product_variant_id' => $variant->getKey(),
            'disk' => 'public',
            'path' => 'products/'.$slug.'.jpg',
            'sort_order' => 1,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'disk' => 'public',
            'path' => 'products/'.$slug.'-hero.jpg',
            'sort_order' => 0,
        ]);

        return ['product' => $product, 'variant' => $variant];
    }

    public function test_list_includes_published_eligible_products_only(): void
    {
        $this->createStorefrontProduct('eligible-one');
        $this->createStorefrontProduct('eligible-two');

        Product::query()->create([
            'name' => 'Draft',
            'slug' => 'draft-item',
            'status' => Product::STATUS_DRAFT,
        ]);

        ['product' => $missingAr] = $this->createStorefrontProduct('missing-ar', arName: 'ignored');
        ProductTranslation::query()->where('product_id', $missingAr->getKey())
            ->where('locale', Product::LOCALE_AR)
            ->delete();

        $page = $this->listQuery->paginate(Product::LOCALE_EN, perPage: 10);

        $this->assertSame(2, $page->total());
        $slugs = $page->getCollection()->pluck('slug')->all();
        $this->assertContains('eligible-one', $slugs);
        $this->assertContains('eligible-two', $slugs);
    }

    public function test_list_returns_only_requested_locale_translation(): void
    {
        $this->createStorefrontProduct('locale-product', enName: 'English Title', arName: 'عنوان عربي');

        $enPage = $this->listQuery->paginate(Product::LOCALE_EN, perPage: 10);
        $product = $enPage->getCollection()->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->translations);
        $this->assertSame('en', $product->translations->first()->locale);
        $this->assertSame('English Title', $product->translations->first()->name);

        $arPage = $this->listQuery->paginate(Product::LOCALE_AR, perPage: 10);
        $productAr = $arPage->getCollection()->first();
        $this->assertSame('ar', $productAr->translations->first()->locale);
        $this->assertSame('عنوان عربي', $productAr->translations->first()->name);
    }

    public function test_list_excludes_product_missing_requested_locale_without_fallback(): void
    {
        $product = Product::query()->create([
            'name' => 'EN Only',
            'slug' => 'en-only',
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => 'English Only',
        ]);

        $size = Size::query()->create(['code' => 'E', 'label' => 'E']);
        $color = Color::query()->create(['name' => 'Red', 'slug' => 'red-en-only']);
        ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'EN-ONLY',
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '10.00',
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        $arPage = $this->listQuery->paginate(Product::LOCALE_AR, perPage: 10);
        $this->assertSame(0, $arPage->total());
    }

    public function test_list_excludes_inactive_variants_and_includes_active(): void
    {
        ['variant' => $variant] = $this->createStorefrontProduct('variant-filter');
        $size = Size::query()->create(['code' => 'L', 'label' => 'Large']);
        $color = Color::query()->create(['name' => 'Grey', 'slug' => 'grey-vf']);

        ProductVariant::query()->create([
            'product_id' => $variant->product_id,
            'sku' => 'SKU-INACTIVE',
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '90.00',
            'status' => ProductVariant::STATUS_INACTIVE,
        ]);

        $page = $this->listQuery->paginate(Product::LOCALE_EN, perPage: 10);
        $product = $page->getCollection()->first();
        $this->assertCount(1, $product->variants);
        $this->assertSame(ProductVariant::STATUS_ACTIVE, $product->variants->first()->status);
    }

    public function test_list_orders_images_and_loads_variant_size_color(): void
    {
        $this->createStorefrontProduct('relations');

        $page = $this->listQuery->paginate(Product::LOCALE_EN, perPage: 10);
        $product = $page->getCollection()->first();

        $this->assertCount(2, $product->images);
        $this->assertSame(0, $product->images->first()->sort_order);
        $this->assertNotNull($product->variants->first()->size);
        $this->assertNotNull($product->variants->first()->color);
    }

    public function test_list_pagination_respects_page_size(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createStorefrontProduct('page-'.$i);
        }

        $page = $this->listQuery->paginate(Product::LOCALE_EN, perPage: 2, page: 2);

        $this->assertSame(3, $page->total());
        $this->assertSame(2, $page->perPage());
        $this->assertSame(2, $page->currentPage());
        $this->assertCount(1, $page->items());
    }

    public function test_get_by_slug_returns_eligible_product(): void
    {
        $this->createStorefrontProduct('by-slug', enName: 'Slug Title');

        $product = $this->bySlugQuery->find('by-slug', Product::LOCALE_EN);

        $this->assertNotNull($product);
        $this->assertSame('by-slug', $product->slug);
        $this->assertSame('Slug Title', $product->translations->first()->name);
    }

    public function test_get_by_slug_returns_null_for_unknown_or_ineligible(): void
    {
        $this->assertNull($this->bySlugQuery->find('missing-slug', Product::LOCALE_EN));

        Product::query()->create([
            'name' => 'Unpublished',
            'slug' => 'unpublished',
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->assertNull($this->bySlugQuery->find('unpublished', Product::LOCALE_EN));

        ['product' => $product] = $this->createStorefrontProduct('soft-deleted');
        $product->delete();
        $this->assertNull($this->bySlugQuery->find('soft-deleted', Product::LOCALE_EN));
    }

    public function test_get_by_slug_excludes_product_without_active_variants(): void
    {
        $product = Product::query()->create([
            'name' => 'No active variants',
            'slug' => 'no-active-variant',
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => 'Name',
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => 'اسم',
        ]);

        $this->assertNull($this->bySlugQuery->find('no-active-variant', Product::LOCALE_EN));
    }

    public function test_list_does_not_n_plus_one_on_relations(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createStorefrontProduct('nplus-'.$i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = $this->listQuery->paginate(Product::LOCALE_EN, perPage: 5);
        foreach ($page as $product) {
            $product->translations->first()?->name;
            foreach ($product->variants as $variant) {
                $variant->size->code;
                $variant->color->slug;
            }
            $product->images->count();
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount);
    }

    public function test_list_active_categories_orders_active_only(): void
    {
        Category::query()->create([
            'name' => 'Inactive',
            'slug' => 'inactive-cat',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        Category::query()->create([
            'name' => 'Active B',
            'slug' => 'active-b',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Category::query()->create([
            'name' => 'Active A',
            'slug' => 'active-a',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $categories = $this->categoriesQuery->list();

        $this->assertCount(2, $categories);
        $this->assertSame('Active A', $categories[0]->name);
        $this->assertSame('Active B', $categories[1]->name);
    }
}
