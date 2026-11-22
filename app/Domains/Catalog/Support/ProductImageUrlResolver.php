<?php

namespace App\Domains\Catalog\Support;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

final class ProductImageUrlResolver
{
    private const GENERIC_PLACEHOLDER = 'demo/catalog/placeholder.svg';

    public function resolveUrl(?ProductImage $image, ?Product $product = null): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = $image->path;

        if ($this->isGenericPlaceholder($path) && $product !== null) {
            $path = $this->categoryPlaceholderPath($product);
        }

        return Storage::disk($image->disk ?: 'public')->url($path);
    }

    public function resolvePrimaryUrl(Product $product): ?string
    {
        $image = $product->images->first();

        if ($image === null) {
            return null;
        }

        return $this->resolveUrl($image, $product);
    }

    private function isGenericPlaceholder(string $path): bool
    {
        return $path === self::GENERIC_PLACEHOLDER || str_ends_with($path, '/placeholder.svg');
    }

    private function categoryPlaceholderPath(Product $product): string
    {
        $slug = (string) ($product->categories->first()?->slug ?? '');

        return match (true) {
            str_contains($slug, 'menswear') => 'demo/catalog/placeholder-mens.svg',
            str_contains($slug, 'womenswear') => 'demo/catalog/placeholder-womens.svg',
            str_contains($slug, 'outerwear') => 'demo/catalog/placeholder-outerwear.svg',
            str_contains($slug, 'accessories') => 'demo/catalog/placeholder-accessories.svg',
            default => self::GENERIC_PLACEHOLDER,
        };
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
