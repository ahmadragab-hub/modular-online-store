<?php

namespace App\Http\Storefront;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\ProductImageUrlResolver;
use App\Domains\Inventory\Queries\GetAvailableQuantityForVariantsQuery;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Queries\ResolveCurrentExchangeRateQuery;
use App\Domains\Store\Services\CheckoutAmountConverter;
use App\Shared\Support\MoneyMath;

final class StorefrontCatalogPresenter
{
    public function __construct(
        private readonly ProductImageUrlResolver $productImageUrlResolver,
        private readonly GetAvailableQuantityForVariantsQuery $availableQuantityForVariantsQuery,
        private readonly ResolveCurrentExchangeRateQuery $exchangeRateQuery,
        private readonly CheckoutAmountConverter $amountConverter,
    ) {}

    /**
     * @return array{
     *     product_id: int,
     *     slug: string,
     *     title: string,
     *     short_description: ?string,
     *     image_url: ?string,
     *     image_alt: string,
     *     from_price_display: string,
     *     display_currency_code: string,
     *     display_currency_symbol: string,
     *     fx_available: bool,
     *     amounts_are_indicative: bool,
     *     variant_summary: string
     * }
     */
    public function toListingCard(Product $product, string $displayCurrencyCode): array
    {
        $translation = $this->requireTranslation($product);
        $primaryImage = $product->images->first();
        $minimumUsd = $this->minimumVariantPriceUsd($product);
        $priceContext = $this->displayPriceContext($minimumUsd, $displayCurrencyCode);

        return [
            'product_id' => $product->getKey(),
            'slug' => $product->slug,
            'title' => $translation->name,
            'short_description' => $translation?->short_description,
            'image_url' => $this->productImageUrlResolver->resolvePrimaryUrl($product),
            'image_alt' => $this->productImageUrlResolver->resolveAltText($product, $primaryImage),
            'from_price_display' => $priceContext['formatted_amount'],
            'display_currency_code' => $priceContext['currency_code'],
            'display_currency_symbol' => $priceContext['symbol'],
            'fx_available' => $priceContext['fx_available'],
            'amounts_are_indicative' => true,
            'variant_summary' => $this->buildVariantSummary($product),
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     short_description: ?string,
     *     display_currency_code: string,
     *     display_currency_symbol: string,
     *     fx_available: bool,
     *     amounts_are_indicative: bool,
     *     images: list<array{url: ?string, alt: string}>,
     *     variants: list<array{
     *         id: int,
     *         sku: string,
     *         size_label: string,
     *         color_name: string,
     *         variant_label: string,
     *         price_display: string,
     *         available_quantity: int,
     *         in_stock: bool
     *     }>
     * }
     */
    public function toProductDetail(Product $product, string $displayCurrencyCode): array
    {
        $translation = $this->requireTranslation($product);
        $variantIds = $product->variants->pluck('id')->all();
        $availability = $this->availableQuantityForVariantsQuery->quantitiesForVariantIds($variantIds);
        $displayContext = $this->resolveDisplayCurrencyContext($displayCurrencyCode);

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
            $priceContext = $this->displayPriceContext((string) $variant->price, $displayCurrencyCode);
            $variants[] = [
                'id' => $variant->getKey(),
                'sku' => $variant->sku,
                'size_label' => $variant->size?->label ?? '',
                'color_name' => $variant->color?->name ?? '',
                'variant_label' => $this->variantLabel($variant),
                'price_display' => $displayContext['symbol'].$priceContext['formatted_amount'],
                'available_quantity' => $available,
                'in_stock' => $available > 0,
            ];
        }

        return [
            'title' => $translation->name,
            'description' => $translation?->description,
            'short_description' => $translation?->short_description,
            'display_currency_code' => $displayContext['currency_code'],
            'display_currency_symbol' => $displayContext['symbol'],
            'fx_available' => $displayContext['fx_available'],
            'amounts_are_indicative' => true,
            'images' => $images,
            'variants' => $variants,
        ];
    }

    private function minimumVariantPriceUsd(Product $product): string
    {
        $prices = $product->variants
            ->map(fn (ProductVariant $variant): string => (string) $variant->price)
            ->filter(fn (string $price): bool => $price !== '')
            ->all();

        if ($prices === []) {
            return '0.00';
        }

        $minimum = $prices[0];
        foreach ($prices as $price) {
            if (MoneyMath::compare($price, $minimum, 2) < 0) {
                $minimum = $price;
            }
        }

        return $minimum;
    }

    /**
     * @return array{currency_code: string, symbol: string, fx_available: bool, minor_units: int}
     */
    private function resolveDisplayCurrencyContext(string $displayCurrencyCode): array
    {
        $displayCurrencyCode = strtoupper(trim($displayCurrencyCode));
        $currency = Currency::query()->find($displayCurrencyCode);
        $fx = $this->exchangeRateQuery->resolve($displayCurrencyCode);
        $fxAvailable = $fx['rate'] !== '' || $displayCurrencyCode === 'USD';

        return [
            'currency_code' => $fxAvailable ? $displayCurrencyCode : 'USD',
            'symbol' => $fxAvailable ? ($currency?->symbol ?? '$') : '$',
            'fx_available' => $fxAvailable,
            'minor_units' => $fxAvailable && $currency !== null ? $currency->minor_units : 2,
        ];
    }

    /**
     * @return array{formatted_amount: string, currency_code: string, symbol: string, fx_available: bool}
     */
    private function displayPriceContext(string $amountUsd, string $displayCurrencyCode): array
    {
        $context = $this->resolveDisplayCurrencyContext($displayCurrencyCode);
        $currency = Currency::query()->find($displayCurrencyCode);
        $fx = $this->exchangeRateQuery->resolve($displayCurrencyCode);

        if (! $context['fx_available'] || $currency === null) {
            return [
                'formatted_amount' => StorefrontMoneyFormatter::format($amountUsd, 2),
                'currency_code' => 'USD',
                'symbol' => '$',
                'fx_available' => false,
            ];
        }

        $converted = $displayCurrencyCode === 'USD'
            ? $amountUsd
            : $this->amountConverter->convertUsdAmount($amountUsd, $currency, $fx['rate']);

        return [
            'formatted_amount' => StorefrontMoneyFormatter::format($converted, $currency->minor_units),
            'currency_code' => $displayCurrencyCode,
            'symbol' => $currency->symbol,
            'fx_available' => true,
        ];
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
}
