<?php

namespace App\Http\Controllers\Web\Storefront;

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
        StorefrontCatalogPresenter $presenter,
    ): View {
        $locale = (string) app()->getLocale();
        $perPage = $request->integer('per_page', 24) ?: 24;

        $products = $listPublishedProductsQuery->paginate(
            locale: $locale,
            perPage: $perPage,
            page: $request->integer('page') ?: null,
        );

        $products->withQueryString();

        $cards = $products->getCollection()
            ->map(fn ($product) => $presenter->toListingCard($product))
            ->values()
            ->all();

        return view('storefront.shop.index', [
            'products' => $products,
            'cards' => $cards,
        ]);
    }
}
