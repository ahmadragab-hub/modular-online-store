<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;

final class UpsertProductTranslationsAction
{
    public function execute(Product $product, string $enName, ?string $enDescription, string $arName, ?string $arDescription): Product
    {
        foreach ([
            Product::LOCALE_EN => ['name' => $enName, 'description' => $enDescription],
            Product::LOCALE_AR => ['name' => $arName, 'description' => $arDescription],
        ] as $locale => $fields) {
            ProductTranslation::query()->updateOrCreate(
                ['product_id' => $product->getKey(), 'locale' => $locale],
                [
                    'name' => trim($fields['name']),
                    'description' => $fields['description'],
                ],
            );
        }

        return $product->fresh(['translations']);
    }
}
