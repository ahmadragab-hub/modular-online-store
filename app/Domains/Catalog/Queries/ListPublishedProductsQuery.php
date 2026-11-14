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
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 100));

        $query = Product::query()
            ->storefrontEligible($locale)
            ->orderByDesc('published_at')
            ->orderBy('id');

        $this->eagerLoadForStorefront($query, $locale);

        return $query->paginate(
            perPage: $perPage,
            page: $page ?? 1,
        );
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
        ]);
    }
}
