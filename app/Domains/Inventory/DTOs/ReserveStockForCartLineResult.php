<?php

namespace App\Domains\Inventory\DTOs;

final class ReserveStockForCartLineResult
{
    public function __construct(
        public int $inventoryReservationId,
        public int $productVariantId,
        public int $quantity,
    ) {}
}
