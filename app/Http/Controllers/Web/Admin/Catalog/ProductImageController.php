<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Domains\Catalog\Actions\DeleteProductImageAction;
use App\Domains\Catalog\Actions\StoreProductImageAction;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Catalog\ProductImageRequest;
use Illuminate\Http\RedirectResponse;

class ProductImageController extends Controller
{
    public function store(Product $product, ProductImageRequest $request, StoreProductImageAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $action->execute(
            product: $product,
            file: $request->file('image'),
            altText: $validated['alt_text'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', __('admin.catalog.images.uploaded'));
    }

    public function destroy(Product $product, ProductImage $image, DeleteProductImageAction $action): RedirectResponse
    {
        abort_unless((int) $image->product_id === (int) $product->getKey(), 404);

        $action->execute($image);

        return back()->with('status', __('admin.catalog.images.deleted'));
    }
}
