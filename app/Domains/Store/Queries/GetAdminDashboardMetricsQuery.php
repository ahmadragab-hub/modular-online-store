<?php

namespace App\Domains\Store\Queries;

use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Orders\Models\Order;
use App\Domains\Store\Models\StoreSetting;
use Illuminate\Support\Collection;

final class GetAdminDashboardMetricsQuery
{
    /**
     * @return array{
     *     order_counts: array<string, int>,
     *     recent_orders: Collection<int, Order>,
     *     low_stock: Collection<int, InventoryLevel>
     * }
     */
    public function execute(int $recentOrderLimit = 10, ?int $lowStockThreshold = null): array
    {
        $settings = StoreSetting::singleton();
        $threshold = $lowStockThreshold ?? 5;

        $statuses = [
            Order::STATUS_PENDING_PAYMENT,
            Order::STATUS_PAYMENT_FAILED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        $orderCounts = [];
        foreach ($statuses as $status) {
            $orderCounts[$status] = Order::query()->where('status', $status)->count();
        }

        $recentOrders = Order::query()
            ->with(['customer'])
            ->orderByDesc('placed_at')
            ->limit($recentOrderLimit)
            ->get();

        $lowStock = InventoryLevel::query()
            ->with(['productVariant'])
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->orderByRaw('(quantity_on_hand - quantity_reserved)')
            ->limit(15)
            ->get();

        return [
            'order_counts' => $orderCounts,
            'recent_orders' => $recentOrders,
            'low_stock' => $lowStock,
            'low_stock_threshold' => $threshold,
            'store_name' => $settings?->store_name,
        ];
    }
}
