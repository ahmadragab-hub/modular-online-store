<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Domains\Catalog\Actions\CreateProductVariantAction;
use App\Domains\Catalog\Actions\UpdateProductVariantAction;
use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Color;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Models\Size;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Catalog\ProductVariantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    public function create(Product $product): View
    {
        return view('admin.catalog.variants.create', [
            'product' => $product,
            'sizes' => Size::query()->orderBy('label')->get(),
            'colors' => Color::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Product $product, ProductVariantRequest $request, CreateProductVariantAction $action): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $action->execute(
                product: $product,
                sku: $validated['sku'],
                sizeId: (int) $validated['size_id'],
                colorId: (int) $validated['color_id'],
                price: $validated['price'],
                status: $validated['status'],
            );
        } catch (CatalogAdminException $exception) {
            return back()->withInput()->withErrors(['sku' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.catalog.saved'));
    }

    public function edit(Product $product, ProductVariant $variant): View
    {
        abort_unless((int) $variant->product_id === (int) $product->getKey(), 404);

        return view('admin.catalog.variants.edit', [
            'product' => $product,
            'variant' => $variant,
            'sizes' => Size::query()->orderBy('label')->get(),
            'colors' => Color::query()->orderBy('name')->get(),
        ]);
    }

    public function update(
        Product $product,
        ProductVariant $variant,
        ProductVariantRequest $request,
        UpdateProductVariantAction $action,
    ): RedirectResponse {
        abort_unless((int) $variant->product_id === (int) $product->getKey(), 404);

        $validated = $request->validated();

        try {
            $action->execute(
                variant: $variant,
                sku: $validated['sku'],
                sizeId: (int) $validated['size_id'],
                colorId: (int) $validated['color_id'],
                price: $validated['price'],
                status: $validated['status'],
            );
        } catch (CatalogAdminException $exception) {
            return back()->withInput()->withErrors(['sku' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.catalog.saved'));
    }
}
