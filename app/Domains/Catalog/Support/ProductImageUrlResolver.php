<?php

namespace App\Domains\Catalog\Support;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

final class ProductImageUrlResolver
{
    public function resolveUrl(?ProductImage $image): ?string
    {
        if ($image === null) {
            return null;
        }

        return Storage::disk($image->disk ?: 'public')->url($image->path);
    }

    public function resolvePrimaryUrl(Product $product): ?string
    {
        $image = $product->images->first();

        return $this->resolveUrl($image);
    }

    public function resolveAltText(Product $product, ?ProductImage $image): string
    {
        if ($image !== null && is_string($image->alt_text) && trim($image->alt_text) !== '') {
            return trim($image->alt_text);
        }

        $translation = $product->translations->first();

        if ($translation === null) {
            throw new \LogicException('Storefront product is missing translation for the active locale.');
        }

        return $translation->name;
    }
}
