<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Catalog\Queries\ListActiveCategoriesQuery;
use App\Domains\Catalog\Queries\ListPublishedProductsQuery;
use App\Http\Controllers\Controller;
use App\Http\Storefront\StorefrontCatalogPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(
        Request $request,
        ListPublishedProductsQuery $listPublishedProductsQuery,
        ListActiveCategoriesQuery $listActiveCategoriesQuery,
        StorefrontCatalogPresenter $presenter,
    ): View {
        $locale = (string) app()->getLocale();
        $perPage = $request->integer('per_page', 24) ?: 24;
        $categorySlug = $request->string('category')->trim()->toString();
        $categorySlug = $categorySlug !== '' ? $categorySlug : null;
        $search = $request->string('q')->trim()->toString();
        $search = $search !== '' ? $search : null;

        $products = $listPublishedProductsQuery->paginate(
            locale: $locale,
            perPage: $perPage,
            page: $request->integer('page') ?: null,
            categorySlug: $categorySlug,
            searchTerm: $search,
        );

        $products->withQueryString();

        $displayCurrency = (string) ($request->attributes->get('storefront.display_currency') ?? 'USD');

        $cards = $products->getCollection()
            ->map(fn ($product) => $presenter->toListingCard($product, $displayCurrency))
            ->values()
            ->all();

        $categories = $listActiveCategoriesQuery->list();
        $activeCategory = $categories->firstWhere('slug', $categorySlug);

        return view('storefront.shop.index', [
            'products' => $products,
            'cards' => $cards,
            'categories' => $categories,
            'activeCategorySlug' => $categorySlug,
            'activeCategoryName' => $activeCategory?->name,
            'searchQuery' => $search,
        ]);
    }
}
