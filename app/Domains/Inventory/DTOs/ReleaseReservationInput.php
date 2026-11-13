<?php

namespace App\Domains\Inventory\DTOs;

final class ReleaseReservationInput
{
    public function __construct(
        public int $reservationId,
    ) {}
}
