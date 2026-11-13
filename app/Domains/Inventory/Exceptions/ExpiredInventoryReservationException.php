<?php

namespace App\Domains\Inventory\Exceptions;

use Carbon\CarbonInterface;
use Exception;

final class ExpiredInventoryReservationException extends Exception
{
    public function __construct(
        public readonly int $reservationId,
        public readonly string $currentStatus,
        public readonly ?CarbonInterface $expiresAt,
        public readonly string $operation = 'use',
    ) {
        parent::__construct(sprintf(
            'Cannot %s expired inventory reservation %d (status "%s").',
            $operation,
            $reservationId,
            $currentStatus,
        ));
    }
}
