<?php

namespace App\Domains\Inventory\DTOs;

final class ExpireReservationsResult
{
    /**
     * @param  list<int>  $expiredReservationIds
     * @param  list<int>  $skippedReservationIds
     * @param  list<int>  $failedReservationIds
     */
    public function __construct(
        public int $candidatesSelected,
        public int $expiredCount,
        public int $skippedCount,
        public int $failedCount,
        public array $expiredReservationIds,
        public array $skippedReservationIds,
        public array $failedReservationIds,
    ) {}
}
