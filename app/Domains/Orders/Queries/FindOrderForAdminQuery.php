<?php

namespace App\Domains\Orders\Queries;

use App\Domains\Orders\Models\Order;

final class FindOrderForAdminQuery
{
    public function execute(int $orderId): ?Order
    {
        return Order::query()
            ->with([
                'customer',
                'user',
                'items',
                'statusHistories.actorUser',
                'payments',
                'reservations',
            ])
            ->whereKey($orderId)
            ->first();
    }
}
