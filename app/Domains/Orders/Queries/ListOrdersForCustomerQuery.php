<?php

namespace App\Domains\Orders\Queries;

use App\Domains\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListOrdersForCustomerQuery
{
    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForCustomer(int $customerId, int $perPage = 15, ?int $page = null): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));

        return Order::query()
            ->where('customer_id', $customerId)
            ->with(['payments' => static fn ($q) => $q->orderByDesc('attempt_number')])
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: $perPage,
                page: $page ?? 1,
            );
    }
}
