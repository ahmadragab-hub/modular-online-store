<?php

namespace Tests\Support;

use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;

trait CreatesStorefrontCatalogProducts
{
    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    protected function createStorefrontProduct(
        string $slug,
        string $enName = 'Product EN',
        string $arName = 'منتج',
        string $variantStatus = ProductVariant::STATUS_ACTIVE,
        string $productStatus = Product::STATUS_PUBLISHED,
        ?int $onHand = 10,
        ?int $reserved = 0,
        bool $withImage = true,
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
            'short_description' => 'Short EN',
            'description' => 'Description EN',
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->getKey(),
            'locale' => Product::LOCALE_AR,
            'name' => $arName,
            'short_description' => 'وصف قصير',
            'description' => 'وصف كامل',
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

        if ($withImage) {
            ProductImage::query()->create([
                'product_id' => $product->getKey(),
                'disk' => 'public',
                'path' => 'products/'.$slug.'.jpg',
                'alt_text' => $enName.' image',
                'sort_order' => 0,
            ]);
        }

        if ($onHand !== null) {
            InventoryLevel::query()->create([
                'product_variant_id' => $variant->getKey(),
                'quantity_on_hand' => $onHand,
                'quantity_reserved' => $reserved ?? 0,
            ]);
        }

        return ['product' => $product, 'variant' => $variant];
    }
}
