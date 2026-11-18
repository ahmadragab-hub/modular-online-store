<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Category;
use Illuminate\Support\Str;

final class UpdateCategoryAction
{
    public function execute(Category $category, string $name, string $slug, ?int $parentId, int $sortOrder, bool $isActive): Category
    {
        if ($parentId === $category->getKey()) {
            throw new CatalogAdminException(
                CatalogAdminException::VALIDATION,
                'Category cannot be its own parent.',
            );
        }

        $category->update([
            'parent_id' => $parentId,
            'name' => trim($name),
            'slug' => Str::slug(trim($slug)),
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        return $category->fresh();
    }
}
