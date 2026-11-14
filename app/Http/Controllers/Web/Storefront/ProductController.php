<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Catalog\Queries\GetProductBySlugQuery;
use App\Http\Controllers\Controller;
use App\Http\Storefront\StorefrontCatalogPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(
        Request $request,
        string $slug,
        GetProductBySlugQuery $getProductBySlugQuery,
        StorefrontCatalogPresenter $presenter,
    ): View {
        $locale = (string) app()->getLocale();
        $product = $getProductBySlugQuery->find($slug, $locale);

        if ($product === null) {
            abort(404);
        }

        $displayCurrency = (string) ($request->attributes->get('storefront.display_currency') ?? 'USD');

        return view('storefront.products.show', [
            'product' => $product,
            'detail' => $presenter->toProductDetail($product, $displayCurrency),
        ]);
    }
}
