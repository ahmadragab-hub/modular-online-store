<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class InvalidInventoryReservationStateException extends Exception
{
    public function __construct(
        public readonly int $reservationId,
        public readonly string $currentStatus,
        public readonly string $operation,
        public readonly ?string $expectedStatus = null,
    ) {
        $expected = $expectedStatus !== null ? " (expected {$expectedStatus})" : '';

        parent::__construct(sprintf(
            'Cannot %s inventory reservation %d in status "%s"%s.',
            $operation,
            $reservationId,
            $currentStatus,
            $expected,
        ));
    }
}
