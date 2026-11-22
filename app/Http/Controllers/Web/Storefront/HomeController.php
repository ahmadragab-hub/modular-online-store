<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Catalog\Queries\ListActiveCategoriesQuery;
use App\Domains\Catalog\Queries\ListPublishedProductsQuery;
use App\Http\Controllers\Controller;
use App\Http\Storefront\StorefrontCatalogPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(
        Request $request,
        ListPublishedProductsQuery $listPublishedProductsQuery,
        ListActiveCategoriesQuery $listActiveCategoriesQuery,
        StorefrontCatalogPresenter $presenter,
    ): View {
        $locale = (string) app()->getLocale();
        $displayCurrency = (string) ($request->attributes->get('storefront.display_currency') ?? 'EGP');

        $featuredPaginator = $listPublishedProductsQuery->paginate(
            locale: $locale,
            perPage: 8,
            page: 1,
        );

        $featuredCards = $featuredPaginator->getCollection()
            ->map(fn ($product) => $presenter->toListingCard($product, $displayCurrency))
            ->values()
            ->all();

        $categories = $listActiveCategoriesQuery->list()->take(8);

        return view('storefront.home', [
            'featuredCards' => $featuredCards,
            'categories' => $categories,
        ]);
    }
}
