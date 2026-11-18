<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait RecordsQueryTableOrder
{
    /**
     * @return list<string>
     */
    protected function recordSelectTableOrder(callable $callback): array
    {
        $tables = [];

        $listener = function ($query) use (&$tables): void {
            $sql = strtolower($query->sql);

            if (! str_starts_with(trim($sql), 'select')) {
                return;
            }

            if (preg_match('/from\s+[`"]?([a-z0-9_]+)[`"]?/i', $query->sql, $matches) !== 1) {
                return;
            }

            $tables[] = $matches[1];
        };

        DB::listen($listener);

        $callback();

        return $tables;
    }

    /**
     * Assert lock-order sequencing after the first order row lock in the recorded queries.
     *
     * @param  list<string>  $tables
     */
    protected function assertLockOrderAfterOrderLock(array $tables, string $before, string $after): void
    {
        $orderLockIndex = array_search('orders', $tables, true);

        $this->assertNotFalse($orderLockIndex, 'Expected SELECT touching orders for lock-order verification.');

        $slice = array_slice($tables, (int) $orderLockIndex);

        $beforeIndex = array_search($before, $slice, true);
        $afterIndex = array_search($after, $slice, true);

        $this->assertNotFalse($beforeIndex, sprintf('Expected SELECT touching table %s after order lock.', $before));
        $this->assertNotFalse($afterIndex, sprintf('Expected SELECT touching table %s after order lock.', $after));
        $this->assertLessThan($afterIndex, $beforeIndex, sprintf(
            'Expected table %s to be queried before %s after order lock.',
            $before,
            $after,
        ));
    }

    /**
     * @param  list<string>  $tables
     */
    protected function assertCartRowLockBeforeReservationLockAfterOrderLock(array $tables): void
    {
        $orderLockIndex = array_search('orders', $tables, true);

        $this->assertNotFalse($orderLockIndex, 'Expected SELECT touching orders for lock-order verification.');

        $slice = array_slice($tables, (int) $orderLockIndex);
        $cartIndex = array_search('carts', $slice, true);

        $this->assertNotFalse($cartIndex, 'Expected SELECT touching carts after order lock.');

        $afterCart = array_slice($slice, (int) $cartIndex + 1);
        $reservationIndex = array_search('inventory_reservations', $afterCart, true);

        $this->assertNotFalse(
            $reservationIndex,
            'Expected reservation SELECT only after at least one cart row lock.',
        );
    }
}
