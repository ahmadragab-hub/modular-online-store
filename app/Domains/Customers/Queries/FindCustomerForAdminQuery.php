<?php

namespace App\Domains\Customers\Queries;

use App\Domains\Customers\Models\Customer;

final class FindCustomerForAdminQuery
{
    public function execute(int $customerId): ?Customer
    {
        return Customer::query()
            ->with([
                'user',
                'addresses',
                'orders' => fn ($q) => $q->orderByDesc('placed_at')->limit(20),
            ])
            ->whereKey($customerId)
            ->first();
    }
}
