<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Catalog\Queries\GetProductBySlugQuery;
use App\Http\Controllers\Controller;
use App\Http\Storefront\StorefrontCatalogPresenter;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(
        string $slug,
        GetProductBySlugQuery $getProductBySlugQuery,
        StorefrontCatalogPresenter $presenter,
    ): View {
        $locale = (string) app()->getLocale();
        $product = $getProductBySlugQuery->find($slug, $locale);

        if ($product === null) {
            abort(404);
        }

        return view('storefront.products.show', [
            'product' => $product,
            'detail' => $presenter->toProductDetail($product),
        ]);
    }
}
