<?php

namespace App\Domains\Catalog\Queries;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListPublishedProductsQuery
{
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(
        string $locale,
        int $perPage = 24,
        ?int $page = null,
        ?string $categorySlug = null,
        ?string $searchTerm = null,
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 100));

        $query = Product::query()
            ->storefrontEligible($locale)
            ->orderByDesc('published_at')
            ->orderBy('id');

        if ($searchTerm !== null && $searchTerm !== '') {
            $this->applySearchTerm($query, $locale, $searchTerm);
        }

        if ($categorySlug !== null && $categorySlug !== '') {
            $query->whereHas('categories', static function (Builder $categoryQuery) use ($categorySlug): void {
                $categoryQuery
                    ->where('slug', $categorySlug)
                    ->where('is_active', true);
            });
        }

        $this->eagerLoadForStorefront($query, $locale);

        return $query->paginate(
            perPage: $perPage,
            page: $page ?? 1,
        );
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySearchTerm(Builder $query, string $locale, string $searchTerm): void
    {
        $normalized = mb_substr(trim($searchTerm), 0, 100);
        if ($normalized === '') {
            return;
        }

        $like = '%'.addcslashes($normalized, '%_\\').'%';

        $query->whereHas('translations', static function (Builder $translationQuery) use ($locale, $like): void {
            $translationQuery
                ->where('locale', $locale)
                ->where(static function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('short_description', 'like', $like);
                });
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function eagerLoadForStorefront(Builder $query, string $locale): void
    {
        $query->with([
            'translations' => static function ($translationQuery) use ($locale): void {
                $translationQuery->where('locale', $locale);
            },
            'variants' => static function ($variantQuery): void {
                $variantQuery
                    ->where('status', ProductVariant::STATUS_ACTIVE)
                    ->orderBy('id')
                    ->with(['size', 'color']);
            },
            'images' => static function ($imageQuery): void {
                $imageQuery->orderBy('sort_order')->orderBy('id');
            },
            'categories',
        ]);
    }
}
