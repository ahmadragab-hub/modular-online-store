<?php

namespace App\Http\Controllers\Web\Admin\Store;

use App\Domains\Store\Actions\UpdateStoreSettingsAction;
use App\Domains\Store\Queries\GetStoreSettingsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\UpdateStoreSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StoreSettingsController extends Controller
{
    public function edit(GetStoreSettingsQuery $query): View
    {
        return view('admin.store.settings.edit', [
            'settings' => $query->execute(),
        ]);
    }

    public function update(UpdateStoreSettingsRequest $request, UpdateStoreSettingsAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', __('admin.store.settings.updated'));
    }
}
