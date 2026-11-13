<?php

namespace Tests\Unit\Domains\Catalog;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StoreLocaleSeeder::class);
    }

    private function createDraftProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Admin Shell',
            'slug' => 'admin-shell-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);
    }

    public function test_category_hierarchy_and_active_scope(): void
    {
        $parent = Category::query()->create([
            'name' => 'Women',
            'slug' => 'women',
            'is_active' => true,
        ]);

        $child = Category::query()->create([
            'parent_id' => $parent->getKey(),
            'name' => 'Dresses',
            'slug' => 'dresses',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Category::class, $child->parent);
        $this->assertSame($parent->getKey(), $child->parent->getKey());
        $this->assertTrue($parent->children()->whereKey($child->getKey())->exists());
        $this->assertSame(2, Category::query()->active()->count());
    }

    public function test_product_translations_for_english_and_arabic(): void
    {
        $product = $this->createDraftProduct();

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_EN,
            'name' => 'Linen Blazer',
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => 'بليزر كتان',
        ]);

        $product->refresh();

        $this->assertCount(2, $product->translations);
        $this->assertNotNull($product->translationEn);
        $this->assertSame('Linen Blazer', $product->translationEn->name);
        $this->assertNotNull($product->translationAr);
        $this->assertSame('بليزر كتان', $product->translationAr->name);
        $this->assertSame('en', $product->translationEn->storeLocale->code);
    }

    public function test_product_translation_unique_per_locale(): void
    {
        $product = $this->createDraftProduct();

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => 'en',
            'name' => 'First',
        ]);

        $this->expectException(QueryException::class);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => 'en',
            'name' => 'Duplicate',
        ]);
    }

    public function test_product_variant_usd_price_and_uniques(): void
    {
        $product = $this->createDraftProduct();
        $size = Size::query()->create(['code' => 'M', 'label' => 'Medium']);
        $color = Color::query()->create(['name' => 'Navy', 'slug' => 'navy']);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'BLZ-NVY-M',
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '199.99',
        ]);
        $variant->refresh();

        $this->assertSame('199.99', (string) $variant->price);
        $this->assertInstanceOf(Product::class, $variant->product);
        $this->assertInstanceOf(Size::class, $variant->size);
        $this->assertInstanceOf(Color::class, $variant->color);

        $this->expectException(QueryException::class);
        ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'BLZ-NVY-M',
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '50.00',
        ]);
    }

    public function test_category_product_pivot_and_product_soft_delete(): void
    {
        $category = Category::query()->create(['name' => 'Outerwear', 'slug' => 'outerwear']);
        $product = $this->createDraftProduct();

        $product->categories()->attach($category->getKey(), ['is_primary' => true]);

        $this->assertTrue($product->categories()->whereKey($category->getKey())->exists());
        $this->assertTrue($category->products()->whereKey($product->getKey())->exists());
        $this->assertTrue((bool) $product->categories()->first()->pivot->is_primary);

        $product->delete();

        $this->assertSoftDeleted('products', ['id' => $product->getKey()]);
    }

    public function test_product_image_relationships(): void
    {
        $product = $this->createDraftProduct();
        $size = Size::query()->create(['code' => 'S', 'label' => 'Small']);
        $color = Color::query()->create(['name' => 'Black', 'slug' => 'black']);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'IMG-SKU',
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '10.00',
        ]);

        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'product_variant_id' => $variant->getKey(),
            'disk' => 'public',
            'path' => 'products/img.jpg',
            'sort_order' => 0,
        ]);

        $this->assertInstanceOf(Product::class, $image->product);
        $this->assertInstanceOf(ProductVariant::class, $image->variant);
        $this->assertTrue($product->images()->whereKey($image->getKey())->exists());
    }

    public function test_product_status_scopes(): void
    {
        $this->createDraftProduct();
        Product::query()->create([
            'name' => 'Live Item',
            'slug' => 'live-item',
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->assertSame(1, Product::query()->draft()->count());
        $this->assertSame(1, Product::query()->published()->count());
    }
}
