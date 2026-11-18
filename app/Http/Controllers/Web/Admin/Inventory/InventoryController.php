<?php

namespace App\Http\Controllers\Web\Admin\Inventory;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Actions\AdjustStockAction;
use App\Domains\Inventory\Exceptions\StockAdjustmentException;
use App\Domains\Inventory\Queries\ListInventoryLevelsForAdminQuery;
use App\Domains\Inventory\Queries\ListInventoryMovementsForAdminQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\AdjustStockRequest;
use App\Http\Requests\Web\Admin\ListInventoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(ListInventoryRequest $request, ListInventoryLevelsForAdminQuery $query): View
    {
        return view('admin.inventory.index', [
            'levels' => $query->execute($request->validated('sku')),
            'filters' => $request->validated(),
        ]);
    }

    public function movements(ListInventoryMovementsForAdminQuery $query): View
    {
        $variantId = request()->integer('product_variant_id') ?: null;

        return view('admin.inventory.movements', [
            'movements' => $query->execute($variantId),
            'productVariantId' => $variantId,
        ]);
    }

    public function adjust(
        AdjustStockRequest $request,
        ProductVariant $variant,
        AdjustStockAction $action,
    ): RedirectResponse {
        try {
            $action->execute(
                productVariantId: (int) $variant->getKey(),
                quantityDelta: (int) $request->validated('quantity_delta'),
                actorUserId: (int) $request->user()->getKey(),
                notes: $request->validated('notes'),
            );
        } catch (StockAdjustmentException) {
            return back()->withErrors(['quantity_delta' => __('admin.inventory.adjust.denied')]);
        }

        return back()->with('status', __('admin.inventory.adjust.success'));
    }
}
