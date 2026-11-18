<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;

final class PublishProductAction
{
    public function execute(Product $product): Product
    {
        $product->loadMissing(['translationEn', 'translationAr', 'variants']);

        if ($product->translationEn === null || $product->translationAr === null) {
            throw new CatalogAdminException(
                CatalogAdminException::VALIDATION,
                'Both English and Arabic translations are required to publish.',
            );
        }

        $hasActiveVariant = $product->variants
            ->contains(fn (ProductVariant $variant): bool => $variant->status === ProductVariant::STATUS_ACTIVE);

        if (! $hasActiveVariant) {
            throw new CatalogAdminException(
                CatalogAdminException::VALIDATION,
                'At least one active variant is required to publish.',
            );
        }

        $product->update([
            'status' => Product::STATUS_PUBLISHED,
            'published_at' => $product->published_at ?? now(),
        ]);

        return $product->fresh();
    }
}
