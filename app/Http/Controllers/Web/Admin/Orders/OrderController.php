<?php

namespace App\Http\Controllers\Web\Admin\Orders;

use App\Domains\Orders\Actions\CancelOrderAction;
use App\Domains\Orders\Actions\TransitionOrderStatusAction;
use App\Domains\Orders\Exceptions\OrderTransitionException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Queries\FindOrderForAdminQuery;
use App\Domains\Orders\Queries\ListOrdersForAdminQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\CancelOrderRequest;
use App\Http\Requests\Web\Admin\ListOrdersRequest;
use App\Http\Requests\Web\Admin\TransitionOrderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, ListOrdersForAdminQuery $query): View
    {
        $filters = $request->validated();

        return view('admin.orders.index', [
            'orders' => $query->execute(
                status: $filters['status'] ?? null,
                orderNumber: $filters['order_number'] ?? null,
                customerEmail: $filters['customer_email'] ?? null,
            ),
            'filters' => $filters,
        ]);
    }

    public function show(Order $order, FindOrderForAdminQuery $query): View
    {
        $order = $query->execute((int) $order->getKey());

        abort_if($order === null, 404);

        return view('admin.orders.show', compact('order'));
    }

    public function transition(
        TransitionOrderRequest $request,
        Order $order,
        TransitionOrderStatusAction $action,
    ): RedirectResponse {
        try {
            $action->execute(
                orderId: (int) $order->getKey(),
                toStatus: $request->validated('to_status'),
                actorUserId: (int) $request->user()->getKey(),
                comment: $request->validated('comment'),
            );
        } catch (OrderTransitionException) {
            return back()->withErrors(['to_status' => __('admin.orders.transition.denied')]);
        }

        return back()->with('status', __('admin.orders.transition.success'));
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrderAction $action): RedirectResponse
    {
        try {
            $action->execute(
                orderId: (int) $order->getKey(),
                actorUserId: (int) $request->user()->getKey(),
                comment: $request->validated('comment'),
            );
        } catch (OrderTransitionException) {
            return back()->withErrors(['cancel' => __('admin.orders.cancel.denied')]);
        }

        return back()->with('status', __('admin.orders.cancel.success'));
    }
}
