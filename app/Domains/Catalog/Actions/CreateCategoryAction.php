<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Category;
use Illuminate\Support\Str;

final class CreateCategoryAction
{
    public function execute(string $name, string $slug, ?int $parentId = null, int $sortOrder = 0, bool $isActive = true): Category
    {
        $slug = Str::slug(trim($slug !== '' ? $slug : $name));

        return Category::query()->create([
            'parent_id' => $parentId,
            'name' => trim($name),
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);
    }
}
