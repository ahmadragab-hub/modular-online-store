<?php

namespace App\Domains\Inventory\DTOs;

final class CommitReservationInput
{
    public function __construct(
        public int $reservationId,
    ) {}
}
