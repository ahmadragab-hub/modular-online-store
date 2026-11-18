<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Http\UploadedFile;

final class StoreProductImageAction
{
    public function execute(Product $product, UploadedFile $file, ?string $altText = null, int $sortOrder = 0): ProductImage
    {
        $path = $file->store('products/'.$product->getKey(), 'public');

        return ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'disk' => 'public',
            'path' => $path,
            'alt_text' => $altText,
            'sort_order' => $sortOrder,
        ]);
    }
}
