<?php

namespace App\Shared\Support;

use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Consistent payment row locks for order-scoped checkout flows (ascending id).
 */
final class OrderScopedPaymentLocks
{
    /**
     * @return Collection<int, Payment>
     */
    public static function lockForOrder(int $orderId): Collection
    {
        return Payment::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    public static function cancelPendingPayments(Collection $payments): void
    {
        foreach ($payments->sortBy('id') as $payment) {
            if ($payment->status !== Payment::STATUS_PENDING) {
                continue;
            }

            $payment->status = Payment::STATUS_CANCELLED;
            $payment->save();
        }
    }
}
