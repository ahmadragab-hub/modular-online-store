<?php

namespace App\Domains\Inventory\DTOs;

use DateTimeInterface;

final class ExpireReservationsInput
{
    public function __construct(
        public int $batchLimit = 100,
        public ?DateTimeInterface $asOf = null,
    ) {}
}
