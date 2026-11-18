<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Str;

final class UpdateProductAction
{
    /**
     * @param  list<int>  $categoryIds
     */
    public function execute(
        Product $product,
        string $name,
        string $slug,
        ?string $brand,
        ?string $description,
        array $categoryIds,
    ): Product {
        $product->update([
            'name' => trim($name),
            'slug' => Str::slug(trim($slug)),
            'brand' => $brand !== null && trim($brand) !== '' ? trim($brand) : null,
            'description' => $description,
        ]);

        $pivot = [];
        foreach ($categoryIds as $index => $categoryId) {
            $pivot[(int) $categoryId] = ['is_primary' => $index === 0];
        }

        $product->categories()->sync($pivot);

        return $product->fresh(['categories']);
    }
}
