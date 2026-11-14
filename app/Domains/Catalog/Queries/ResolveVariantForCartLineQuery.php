<?php

namespace App\Domains\Catalog\Queries;

use App\Domains\Catalog\Exceptions\VariantUnavailableForCartException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;

final class ResolveVariantForCartLineQuery
{
    public function resolve(int $productVariantId, string $contentLocale): ProductVariant
    {
        $contentLocale = trim($contentLocale);

        if ($productVariantId < 1) {
            throw new VariantUnavailableForCartException(
                VariantUnavailableForCartException::NOT_FOUND,
                'The selected product variant is not available.',
            );
        }

        if ($contentLocale === '') {
            throw new VariantUnavailableForCartException(
                VariantUnavailableForCartException::TRANSLATION_MISSING,
                'The product translation for this locale is not available.',
            );
        }

        $variant = ProductVariant::query()
            ->with([
                'size',
                'color',
                'product.translations' => static function ($translationQuery) use ($contentLocale): void {
                    $translationQuery->whereIn('locale', [
                        Product::LOCALE_EN,
                        Product::LOCALE_AR,
                        $contentLocale,
                    ]);
                },
            ])
            ->find($productVariantId);

        if ($variant === null) {
            throw new VariantUnavailableForCartException(
                VariantUnavailableForCartException::NOT_FOUND,
                'The selected product variant is not available.',
            );
        }

        if ($variant->status !== ProductVariant::STATUS_ACTIVE) {
            throw new VariantUnavailableForCartException(
                VariantUnavailableForCartException::INACTIVE,
                'The selected product variant is not available.',
            );
        }

        $product = $variant->product;
        if ($product === null || $product->status !== Product::STATUS_PUBLISHED) {
            throw new VariantUnavailableForCartException(
                VariantUnavailableForCartException::PRODUCT_UNAVAILABLE,
                'The selected product is not available for purchase.',
            );
        }

        $this->assertTranslationPresent($product, Product::LOCALE_EN);
        $this->assertTranslationPresent($product, Product::LOCALE_AR);
        $this->assertTranslationPresent($product, $contentLocale);

        return $variant;
    }

    private function assertTranslationPresent(Product $product, string $locale): void
    {
        $translation = $product->translations->first(
            static fn (ProductTranslation $row): bool => $row->locale === $locale,
        );

        if ($translation === null || trim($translation->name) === '') {
            throw new VariantUnavailableForCartException(
                VariantUnavailableForCartException::TRANSLATION_MISSING,
                'The product translation for this locale is not available.',
            );
        }
    }
}
