<?php

namespace App\Http\Storefront;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\ProductImageUrlResolver;
use App\Domains\Inventory\Queries\GetAvailableQuantityForVariantsQuery;
use App\Shared\Support\MoneyMath;

final class StorefrontCatalogPresenter
{
    public function __construct(
        private readonly ProductImageUrlResolver $productImageUrlResolver,
        private readonly GetAvailableQuantityForVariantsQuery $availableQuantityForVariantsQuery,
    ) {}

    /**
     * @return array{
     *     product_id: int,
     *     slug: string,
     *     title: string,
     *     short_description: ?string,
     *     image_url: ?string,
     *     image_alt: string,
     *     from_price_usd: string,
     *     variant_summary: string
     * }
     */
    public function toListingCard(Product $product): array
    {
        $translation = $this->requireTranslation($product);
        $primaryImage = $product->images->first();

        return [
            'product_id' => $product->getKey(),
            'slug' => $product->slug,
            'title' => $translation->name,
            'short_description' => $translation?->short_description,
            'image_url' => $this->productImageUrlResolver->resolvePrimaryUrl($product),
            'image_alt' => $this->productImageUrlResolver->resolveAltText($product, $primaryImage),
            'from_price_usd' => $this->formatFromPriceUsd($product),
            'variant_summary' => $this->buildVariantSummary($product),
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     short_description: ?string,
     *     images: list<array{url: ?string, alt: string}>,
     *     variants: list<array{
     *         id: int,
     *         sku: string,
     *         size_label: string,
     *         color_name: string,
     *         variant_label: string,
     *         price_usd: string,
     *         available_quantity: int,
     *         in_stock: bool
     *     }>
     * }
     */
    public function toProductDetail(Product $product): array
    {
        $translation = $this->requireTranslation($product);
        $variantIds = $product->variants->pluck('id')->all();
        $availability = $this->availableQuantityForVariantsQuery->quantitiesForVariantIds($variantIds);

        $images = [];
        foreach ($product->images as $image) {
            $images[] = [
                'url' => $this->productImageUrlResolver->resolveUrl($image),
                'alt' => $this->productImageUrlResolver->resolveAltText($product, $image),
            ];
        }

        $variants = [];
        foreach ($product->variants as $variant) {
            $available = $availability[$variant->getKey()] ?? 0;
            $variants[] = [
                'id' => $variant->getKey(),
                'sku' => $variant->sku,
                'size_label' => $variant->size?->label ?? '',
                'color_name' => $variant->color?->name ?? '',
                'variant_label' => $this->variantLabel($variant),
                'price_usd' => $this->formatUsdAmount((string) $variant->price),
                'available_quantity' => $available,
                'in_stock' => $available > 0,
            ];
        }

        return [
            'title' => $translation->name,
            'description' => $translation?->description,
            'short_description' => $translation?->short_description,
            'images' => $images,
            'variants' => $variants,
        ];
    }

    private function formatFromPriceUsd(Product $product): string
    {
        $prices = $product->variants
            ->map(fn (ProductVariant $variant): string => (string) $variant->price)
            ->filter(fn (string $price): bool => $price !== '')
            ->all();

        if ($prices === []) {
            return $this->formatUsdAmount('0');
        }

        $minimum = $prices[0];
        foreach ($prices as $price) {
            if (MoneyMath::compare($price, $minimum, 2) < 0) {
                $minimum = $price;
            }
        }

        return $this->formatUsdAmount($minimum);
    }

    private function requireTranslation(Product $product): ProductTranslation
    {
        $translation = $product->translations->first();

        if ($translation === null) {
            throw new \LogicException('Storefront product is missing translation for the active locale.');
        }

        return $translation;
    }

    private function buildVariantSummary(Product $product): string
    {
        $parts = [];
        foreach ($product->variants as $variant) {
            $parts[] = $this->variantLabel($variant);
        }

        return implode(' · ', array_unique($parts));
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $size = $variant->size?->label ?? '';
        $color = $variant->color?->name ?? '';

        if ($size !== '' && $color !== '') {
            return $size.' / '.$color;
        }

        return $size !== '' ? $size : $color;
    }

    private function formatUsdAmount(string $amount): string
    {
        return '$'.number_format((float) $amount, 2, '.', ',');
    }
}
