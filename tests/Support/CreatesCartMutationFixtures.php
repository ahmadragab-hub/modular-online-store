<?php

namespace Tests\Support;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Support\Str;

trait CreatesCartMutationFixtures
{
    /**
     * @param  list<string>|null  $locales
     * @return array{product: Product, variant: ProductVariant}
     */
    protected function createSellableVariantForCart(
        string $priceUsd = '50.00',
        ?int $stock = 10,
        int $reserved = 0,
        ?array $locales = null,
        string $productStatus = Product::STATUS_PUBLISHED,
        string $variantStatus = ProductVariant::STATUS_ACTIVE,
        string $enName = 'Cart Product EN',
        string $arName = 'منتج السلة',
    ): array {
        $product = Product::query()->create([
            'name' => 'Cart Base Name',
            'slug' => 'cart-product-'.uniqid(),
            'status' => $productStatus,
            'published_at' => $productStatus === Product::STATUS_PUBLISHED ? now() : null,
        ]);

        $locales ??= [Product::LOCALE_EN, Product::LOCALE_AR];
        foreach ($locales as $locale) {
            ProductTranslation::query()->create([
                'product_id' => $product->getKey(),
                'locale' => $locale,
                'name' => match ($locale) {
                    Product::LOCALE_AR => $arName,
                    default => $enName,
                },
            ]);
        }

        $size = Size::query()->create(['code' => 'C-'.uniqid(), 'label' => 'Large']);
        $color = Color::query()->create(['name' => 'Navy', 'slug' => 'navy-'.uniqid()]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CART-'.uniqid(),
            'size_id' => $size->getKey(),
            'color_id' => $color->getKey(),
            'price' => $priceUsd,
            'status' => $variantStatus,
        ]);

        if ($stock !== null) {
            InventoryLevel::query()->create([
                'product_variant_id' => $variant->getKey(),
                'quantity_on_hand' => $stock,
                'quantity_reserved' => $reserved,
            ]);
        }

        return ['product' => $product, 'variant' => $variant];
    }

    protected function createActiveGuestCart(?\DateTimeInterface $expiresAt = null): Cart
    {
        return Cart::query()->create([
            'session_token' => hash('sha256', Str::random(32)),
            'display_currency_code' => 'USD',
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => $expiresAt,
        ]);
    }
}
