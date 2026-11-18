<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Domains\Catalog\Actions\CreateColorAction;
use App\Domains\Catalog\Models\Color;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Catalog\ColorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ColorController extends Controller
{
    public function index(): View
    {
        $colors = Color::query()->orderBy('name')->paginate(50);

        return view('admin.catalog.colors.index', compact('colors'));
    }

    public function store(ColorRequest $request, CreateColorAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $action->execute($validated['name'], $validated['slug'] ?? null);

        return back()->with('status', __('admin.catalog.saved'));
    }
}
