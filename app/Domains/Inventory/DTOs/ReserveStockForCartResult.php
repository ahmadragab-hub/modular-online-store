<?php

namespace App\Domains\Inventory\DTOs;

final class ReserveStockForCartResult
{
    /**
     * @param  list<ReserveStockForCartLineResult>  $lines
     */
    public function __construct(
        public int $cartId,
        public array $lines,
        public bool $reusedExistingReservations,
    ) {}
}
