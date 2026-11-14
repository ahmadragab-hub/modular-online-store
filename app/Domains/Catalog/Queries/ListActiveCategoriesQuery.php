<?php

namespace App\Domains\Catalog\Queries;

use App\Domains\Catalog\Models\Category;
use Illuminate\Support\Collection;

/**
 * Lists active categories for storefront navigation.
 *
 * Localization: category names use the single `categories.name` column (English/admin copy).
 * Category translations are not in the MVP schema.
 */
final class ListActiveCategoriesQuery
{
    /**
     * @return Collection<int, Category>
     */
    public function list(): Collection
    {
        return Category::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
