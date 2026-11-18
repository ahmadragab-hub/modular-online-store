<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Queries\ListOrdersForCustomerQuery;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Storefront\Concerns\ResolvesStorefrontCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerOrderController extends Controller
{
    use ResolvesStorefrontCustomer;

    public function index(Request $request, ListOrdersForCustomerQuery $listOrdersForCustomerQuery): View
    {
        $customer = $this->currentCustomer();
        $perPage = $request->integer('per_page', 15) ?: 15;

        $orders = $listOrdersForCustomerQuery->paginateForCustomer(
            (int) $customer->getKey(),
            $perPage,
            $request->integer('page') ?: null,
        );

        $orders->withQueryString();

        return view('storefront.account.orders.index', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }

    public function show(string $publicUuid): View
    {
        $customer = $this->currentCustomer();

        /** @var Order|null $order */
        $order = Order::query()
            ->where('public_uuid', $publicUuid)
            ->with([
                'items',
                'payments' => static fn ($q) => $q->orderByDesc('attempt_number'),
            ])
            ->first();

        if ($order === null || (int) $order->customer_id !== (int) $customer->getKey()) {
            abort(404);
        }

        return view('storefront.account.orders.show', [
            'customer' => $customer,
            'order' => $order,
            'latestPayment' => $order->payments->first(),
        ]);
    }
}
