<?php

namespace Tests\Unit\Domains\Catalog;

use App\Domains\Catalog\Exceptions\VariantUnavailableForCartException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Queries\ResolveVariantForCartLineQuery;
use Database\Seeders\StoreLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCartMutationFixtures;
use Tests\TestCase;

class ResolveVariantForCartLineQueryTest extends TestCase
{
    use CreatesCartMutationFixtures;
    use RefreshDatabase;

    private ResolveVariantForCartLineQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StoreLocaleSeeder::class);
        $this->query = $this->app->make(ResolveVariantForCartLineQuery::class);
    }

    public function test_resolves_valid_active_variant(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart();

        $resolved = $this->query->resolve($variant->getKey(), Product::LOCALE_EN);

        $this->assertSame($variant->getKey(), $resolved->getKey());
        $this->assertNotNull($resolved->product);
        $this->assertNotNull($resolved->size);
        $this->assertNotNull($resolved->color);
    }

    public function test_rejects_inactive_variant(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            variantStatus: ProductVariant::STATUS_INACTIVE,
        );

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), Product::LOCALE_EN);
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::INACTIVE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_draft_product(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            productStatus: Product::STATUS_DRAFT,
        );

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), Product::LOCALE_EN);
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::PRODUCT_UNAVAILABLE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_archived_product(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            productStatus: Product::STATUS_ARCHIVED,
        );

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), Product::LOCALE_EN);
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::PRODUCT_UNAVAILABLE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_soft_deleted_product(): void
    {
        ['product' => $product, 'variant' => $variant] = $this->createSellableVariantForCart();
        $product->delete();

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), Product::LOCALE_EN);
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::PRODUCT_UNAVAILABLE, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_missing_english_translation(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            locales: [Product::LOCALE_AR],
        );

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), Product::LOCALE_AR);
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::TRANSLATION_MISSING, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_missing_arabic_translation(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            locales: [Product::LOCALE_EN],
        );

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), Product::LOCALE_EN);
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::TRANSLATION_MISSING, $exception->errorCode);

            throw $exception;
        }
    }

    public function test_rejects_missing_requested_locale_translation(): void
    {
        ['variant' => $variant] = $this->createSellableVariantForCart(
            locales: [Product::LOCALE_EN, Product::LOCALE_AR],
        );

        $this->expectException(VariantUnavailableForCartException::class);

        try {
            $this->query->resolve($variant->getKey(), 'fr');
        } catch (VariantUnavailableForCartException $exception) {
            $this->assertSame(VariantUnavailableForCartException::TRANSLATION_MISSING, $exception->errorCode);

            throw $exception;
        }
    }
}
