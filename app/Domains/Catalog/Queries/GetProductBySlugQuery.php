<?php

namespace App\Domains\Catalog\Queries;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

final class GetProductBySlugQuery
{
    public function find(string $slug, string $locale): ?Product
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $query = Product::query()
            ->where('slug', $slug)
            ->storefrontEligible($locale);

        $this->eagerLoadForStorefront($query, $locale);

        return $query->first();
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
