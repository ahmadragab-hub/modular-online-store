<?php

namespace App\Domains\Inventory\DTOs;

final class ReserveStockForCartLineInput
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
    ) {}
}
