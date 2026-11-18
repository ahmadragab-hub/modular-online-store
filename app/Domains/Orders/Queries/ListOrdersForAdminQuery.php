<?php

namespace App\Domains\Orders\Queries;

use App\Domains\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListOrdersForAdminQuery
{
    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function execute(
        ?string $status = null,
        ?string $orderNumber = null,
        ?string $customerEmail = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Order::query()
            ->with(['customer', 'user'])
            ->orderByDesc('placed_at');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($orderNumber !== null && $orderNumber !== '') {
            $query->where('order_number', 'like', '%'.addcslashes($orderNumber, '%_\\').'%');
        }

        if ($customerEmail !== null && $customerEmail !== '') {
            $email = strtolower(trim($customerEmail));
            $query->where(function ($builder) use ($email): void {
                $builder->whereRaw('LOWER(guest_email) like ?', ['%'.addcslashes($email, '%_\\').'%'])
                    ->orWhereHas('customer', fn ($q) => $q->whereRaw('LOWER(email) like ?', ['%'.addcslashes($email, '%_\\').'%']));
            });
        }

        return $query->paginate($perPage);
    }
}
