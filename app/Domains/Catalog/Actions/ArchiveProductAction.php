<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;

final class ArchiveProductAction
{
    public function execute(Product $product): Product
    {
        $product->update([
            'status' => Product::STATUS_ARCHIVED,
        ]);

        return $product->fresh();
    }
}
