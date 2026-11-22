<?php

namespace Tests\Unit\Domains\Catalog;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Support\ProductImageUrlResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ProductImageUrlResolverTest extends TestCase
{
    private ProductImageUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->resolver = new ProductImageUrlResolver;
    }

    public function test_empty_disk_resolves_url_using_public_disk(): void
    {
        Storage::disk('public')->put('products/fallback.jpg', 'bytes');

        $image = new ProductImage([
            'disk' => '',
            'path' => 'products/fallback.jpg',
        ]);

        $expected = Storage::disk('public')->url('products/fallback.jpg');

        $this->assertSame($expected, $this->resolver->resolveUrl($image));
    }

    public function test_null_disk_resolves_url_using_public_disk(): void
    {
        Storage::disk('public')->put('products/null-disk.jpg', 'bytes');

        $image = new ProductImage([
            'path' => 'products/null-disk.jpg',
        ]);
        $image->disk = null;

        $expected = Storage::disk('public')->url('products/null-disk.jpg');

        $this->assertSame($expected, $this->resolver->resolveUrl($image));
    }

    public function test_alt_text_uses_translation_name_when_image_has_no_alt_text(): void
    {
        $product = new Product(['name' => 'Base product name']);
        $product->setRelation('translations', new Collection([
            new ProductTranslation(['name' => 'Localized title']),
        ]));

        $image = new ProductImage(['alt_text' => '']);

        $this->assertSame('Localized title', $this->resolver->resolveAltText($product, $image));
    }

    public function test_alt_text_preserves_image_alt_text_when_present(): void
    {
        $product = new Product(['name' => 'Base product name']);
        $product->setRelation('translations', new Collection([
            new ProductTranslation(['name' => 'Localized title']),
        ]));

        $image = new ProductImage(['alt_text' => '  Custom alt  ']);

        $this->assertSame('Custom alt', $this->resolver->resolveAltText($product, $image));
    }

    public function test_generic_placeholder_maps_to_category_asset(): void
    {
        Storage::disk('public')->put('demo/catalog/placeholder.svg', 'x');
        Storage::disk('public')->put('demo/catalog/placeholder-mens.svg', 'x');

        $product = new Product;
        $product->setRelation('categories', new Collection([
            new Category(['slug' => 'demo-menswear']),
        ]));

        $image = new ProductImage([
            'disk' => 'public',
            'path' => 'demo/catalog/placeholder.svg',
        ]);

        $expected = Storage::disk('public')->url('demo/catalog/placeholder-mens.svg');
        $this->assertSame($expected, $this->resolver->resolveUrl($image, $product));
    }

    public function test_alt_text_does_not_fall_back_to_base_product_name(): void
    {
        $product = new Product(['name' => 'Base product name']);
        $product->setRelation('translations', new Collection);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Storefront product is missing translation for the active locale.');

        $this->resolver->resolveAltText($product, null);
    }
}
