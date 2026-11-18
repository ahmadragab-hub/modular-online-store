<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Actions\InitializeVariantStockAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateProductVariantAction
{
    public function __construct(
        private readonly InitializeVariantStockAction $initializeVariantStockAction,
    ) {}

    public function execute(
        Product $product,
        string $sku,
        int $sizeId,
        int $colorId,
        string $price,
        string $status = ProductVariant::STATUS_ACTIVE,
    ): ProductVariant {
        $sku = strtoupper(trim($sku));

        return DB::transaction(function () use ($product, $sku, $sizeId, $colorId, $price, $status): ProductVariant {
            try {
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->getKey(),
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

            $this->initializeVariantStockAction->execute($variant->getKey(), 0);

            return $variant;
        });
    }
}
