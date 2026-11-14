<?php

namespace App\Domains\Cart\Support;

use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;

final class CartLineSnapshots
{
    /**
     * @return array{
     *     unit_price_base: string,
     *     product_name: string,
     *     sku: string,
     *     variant_label: string
     * }
     */
    public static function fromVariant(ProductVariant $variant, string $contentLocale): array
    {
        $product = $variant->product;
        $translation = $product?->translations->first(
            static fn (ProductTranslation $row): bool => $row->locale === $contentLocale,
        );

        if ($translation === null) {
            throw new \LogicException('Cart line snapshot requires locale translation loaded by ResolveVariantForCartLineQuery.');
        }

        return [
            'unit_price_base' => (string) $variant->price,
            'product_name' => $translation->name,
            'sku' => $variant->sku,
            'variant_label' => self::variantLabel($variant),
        ];
    }

    private static function variantLabel(ProductVariant $variant): string
    {
        $size = $variant->size?->label ?? '';
        $color = $variant->color?->name ?? '';

        if ($size !== '' && $color !== '') {
            return $size.' / '.$color;
        }

        return $size !== '' ? $size : $color;
    }
}
