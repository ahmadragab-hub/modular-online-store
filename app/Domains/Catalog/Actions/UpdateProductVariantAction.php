<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\QueryException;

final class UpdateProductVariantAction
{
    public function execute(
        ProductVariant $variant,
        string $sku,
        int $sizeId,
        int $colorId,
        string $price,
        string $status,
    ): ProductVariant {
        $sku = strtoupper(trim($sku));

        try {
            $variant->update([
                'sku' => $sku,
                'size_id' => $sizeId,
                'color_id' => $colorId,
                'price' => $price,
                'status' => $status,
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'sku')) {
                throw new CatalogAdminException(
                    CatalogAdminException::DUPLICATE_SKU,
                    'SKU is already in use.',
                );
            }

            throw $exception;
        }

        return $variant->fresh();
    }
}
