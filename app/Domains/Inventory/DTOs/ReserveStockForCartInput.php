<?php

namespace App\Domains\Inventory\DTOs;

use DateTimeInterface;

final class ReserveStockForCartInput
{
    /**
     * @param  list<ReserveStockForCartLineInput>  $lines
     */
    public function __construct(
        public int $cartId,
        public array $lines,
        public DateTimeInterface $expiresAt,
    ) {}
}
