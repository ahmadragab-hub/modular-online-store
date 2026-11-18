<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Domains\Catalog\Actions\ArchiveProductAction;
use App\Domains\Catalog\Actions\CreateProductAction;
use App\Domains\Catalog\Actions\PublishProductAction;
use App\Domains\Catalog\Actions\UpdateProductAction;
use App\Domains\Catalog\Actions\UpsertProductTranslationsAction;
use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Catalog\ProductRequest;
use App\Http\Requests\Web\Admin\Catalog\ProductTranslationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()->withCount('variants')->orderByDesc('updated_at')->paginate(20);

        return view('admin.catalog.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.catalog.products.create');
    }

    public function store(ProductRequest $request, CreateProductAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $product = $action->execute($validated['name'], $validated['slug'] ?? '');

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.catalog.saved'));
    }

    public function edit(Product $product): View
    {
        $product->load(['translations', 'variants.size', 'variants.color', 'images', 'categories']);

        return view('admin.catalog.products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Product $product, ProductRequest $request, UpdateProductAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $action->execute(
            product: $product,
            name: $validated['name'],
            slug: $validated['slug'] ?? '',
            brand: $validated['brand'] ?? null,
            description: $validated['description'] ?? null,
            categoryIds: $validated['category_ids'] ?? [],
        );

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.catalog.saved'));
    }

    public function updateTranslations(
        Product $product,
        ProductTranslationRequest $request,
        UpsertProductTranslationsAction $action,
    ): RedirectResponse {
        $validated = $request->validated();
        $action->execute(
            product: $product,
            enName: $validated['en_name'],
            enDescription: $validated['en_description'] ?? null,
            arName: $validated['ar_name'],
            arDescription: $validated['ar_description'] ?? null,
        );

        return back()->with('status', __('admin.catalog.translations.saved'));
    }

    public function publish(Product $product, PublishProductAction $action): RedirectResponse
    {
        try {
            $action->execute($product);
        } catch (CatalogAdminException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return back()->with('status', __('admin.catalog.products.published'));
    }

    public function archive(Product $product, ArchiveProductAction $action): RedirectResponse
    {
        $action->execute($product);

        return back()->with('status', __('admin.catalog.products.archived'));
    }
}
