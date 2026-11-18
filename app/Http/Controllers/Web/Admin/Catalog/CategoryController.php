<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Domains\Catalog\Actions\CreateCategoryAction;
use App\Domains\Catalog\Actions\UpdateCategoryAction;
use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Catalog\CategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->with('parent')->orderBy('sort_order')->paginate(25);

        return view('admin.catalog.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.catalog.categories.create', [
            'parents' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(CategoryRequest $request, CreateCategoryAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $category = $action->execute(
            name: $validated['name'],
            slug: $validated['slug'] ?? '',
            parentId: $validated['parent_id'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            isActive: (bool) ($validated['is_active'] ?? true),
        );

        return redirect()->route('admin.categories.edit', $category)->with('status', __('admin.catalog.saved'));
    }

    public function edit(Category $category): View
    {
        return view('admin.catalog.categories.edit', [
            'category' => $category,
            'parents' => Category::query()->whereKeyNot($category->getKey())->orderBy('name')->get(),
        ]);
    }

    public function update(Category $category, CategoryRequest $request, UpdateCategoryAction $action): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $action->execute(
                category: $category,
                name: $validated['name'],
                slug: $validated['slug'] ?? '',
                parentId: $validated['parent_id'] ?? null,
                sortOrder: (int) ($validated['sort_order'] ?? 0),
                isActive: (bool) ($validated['is_active'] ?? true),
            );
        } catch (CatalogAdminException $exception) {
            return back()->withErrors(['parent_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.categories.edit', $category)->with('status', __('admin.catalog.saved'));
    }
}
