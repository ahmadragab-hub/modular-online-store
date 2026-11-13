<?php

namespace App\Domains\Inventory\DTOs;

final class ReleaseReservationResult
{
    public function __construct(
        public int $reservationId,
        public int $productVariantId,
        public int $quantity,
        public int $quantityOnHandAfter,
        public bool $idempotentReplay,
    ) {}
}
