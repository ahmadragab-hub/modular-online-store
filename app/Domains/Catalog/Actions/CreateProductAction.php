<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Str;

final class CreateProductAction
{
    public function execute(string $name, string $slug): Product
    {
        $slug = Str::slug(trim($slug !== '' ? $slug : $name));

        return Product::query()->create([
            'name' => trim($name),
            'slug' => $slug,
            'status' => Product::STATUS_DRAFT,
        ]);
    }
}
