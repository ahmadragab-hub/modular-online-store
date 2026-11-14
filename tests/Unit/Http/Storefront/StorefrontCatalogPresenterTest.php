<?php

namespace Tests\Unit\Http\Storefront;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Http\Storefront\StorefrontCatalogPresenter;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\StoreLocaleSeeder;
use Database\Seeders\StoreSettingsSeeder;
use Database\Seeders\StoreThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use LogicException;
use Tests\TestCase;

class StorefrontCatalogPresenterTest extends TestCase
{
    use RefreshDatabase;

    private StorefrontCatalogPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            StoreLocaleSeeder::class,
            StoreThemeSeeder::class,
            StoreSettingsSeeder::class,
        ]);

        $this->presenter = $this->app->make(StorefrontCatalogPresenter::class);
    }

    public function test_listing_card_uses_localized_translation_for_title(): void
    {
        $product = $this->productWithTranslation('Arabic title', 'Base name');
        $product->setRelation('images', new Collection);
        $product->setRelation('variants', new Collection([
            $this->variantWithPrice('50.00'),
        ]));

        $card = $this->presenter->toListingCard($product, 'USD');

        $this->assertSame('Arabic title', $card['title']);
        $this->assertSame('Arabic title', $card['image_alt']);
    }

    public function test_listing_card_does_not_fall_back_to_base_product_name(): void
    {
        $product = new Product(['name' => 'Base name', 'slug' => 'slug']);
        $product->setRelation('translations', new Collection);
        $product->setRelation('images', new Collection);
        $product->setRelation('variants', new Collection([
            $this->variantWithPrice('50.00'),
        ]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Storefront product is missing translation for the active locale.');

        $this->presenter->toListingCard($product, 'USD');
    }

    public function test_from_price_uses_decimal_safe_minimum_comparison(): void
    {
        $product = $this->productWithTranslation('Title', 'Base');
        $product->setRelation('images', new Collection);
        $product->setRelation('variants', new Collection([
            $this->variantWithPrice('100.00'),
            $this->variantWithPrice('99.99'),
            $this->variantWithPrice('100000000.29'),
        ]));

        $card = $this->presenter->toListingCard($product, 'USD');

        $this->assertSame('99.99', $card['from_price_display']);
        $this->assertSame('$', $card['display_currency_symbol']);
    }

    public function test_egp_display_conversion_without_float(): void
    {
        CurrencyExchangeRate::query()->create([
            'quote_currency_code' => 'EGP',
            'rate' => '48.50000000',
            'effective_from' => now()->subDay(),
            'created_at' => now(),
        ]);

        $product = $this->productWithTranslation('Title', 'Base');
        $product->setRelation('images', new Collection);
        $product->setRelation('variants', new Collection([
            $this->variantWithPrice('10.00'),
        ]));

        $card = $this->presenter->toListingCard($product, 'EGP');

        $this->assertSame('EGP', $card['display_currency_code']);
        $this->assertSame('485.00', $card['from_price_display']);
    }

    private function productWithTranslation(string $localizedName, string $baseName): Product
    {
        $product = new Product(['name' => $baseName, 'slug' => 'slug']);
        $product->setRelation('translations', new Collection([
            new ProductTranslation(['name' => $localizedName, 'short_description' => null]),
        ]));

        return $product;
    }

    private function variantWithPrice(string $price): ProductVariant
    {
        return new ProductVariant(['price' => $price, 'sku' => 'SKU', 'status' => ProductVariant::STATUS_ACTIVE]);
    }
}
