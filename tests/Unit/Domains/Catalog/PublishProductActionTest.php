<?php

namespace Tests\Unit\Domains\Catalog;

use App\Domains\Catalog\Actions\PublishProductAction;
use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishProductActionTest extends TestCase
{
    use RefreshDatabase;

    private PublishProductAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreLocaleSeeder::class);
        $this->action = $this->app->make(PublishProductAction::class);
    }

    public function test_requires_both_translations_and_active_variant(): void
    {
        $product = Product::query()->create([
            'name' => 'Draft',
            'slug' => 'draft-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->expectException(CatalogAdminException::class);
        $this->action->execute($product);
    }

    public function test_publishes_when_requirements_met(): void
    {
        $product = Product::query()->create([
            'name' => 'Ready',
            'slug' => 'ready-'.uniqid(),
            'status' => Product::STATUS_DRAFT,
        ]);

        ProductTranslation::query()->create(['product_id' => $product->getKey(), 'locale' => 'en', 'name' => 'EN']);
        ProductTranslation::query()->create(['product_id' => $product->getKey(), 'locale' => 'ar', 'name' => 'AR']);

        $size = Size::query()->create(['code' => 'S', 'label' => 'Small']);
        $color = Color::query()->create(['name' => 'Black', 'slug' => 'black-'.uniqid()]);

        ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'SKU-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => '10.00',
            'status' => ProductVariant::STATUS_ACTIVE,
        ]);

        $published = $this->action->execute($product);

        $this->assertSame(Product::STATUS_PUBLISHED, $published->status);
    }
}
