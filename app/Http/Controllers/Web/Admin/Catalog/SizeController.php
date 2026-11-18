<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Domains\Catalog\Actions\CreateSizeAction;
use App\Domains\Catalog\Models\Size;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Catalog\SizeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SizeController extends Controller
{
    public function index(): View
    {
        $sizes = Size::query()->orderBy('label')->paginate(50);

        return view('admin.catalog.sizes.index', compact('sizes'));
    }

    public function store(SizeRequest $request, CreateSizeAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $action->execute($validated['label'], $validated['code'] ?? null);

        return back()->with('status', __('admin.catalog.saved'));
    }
}
