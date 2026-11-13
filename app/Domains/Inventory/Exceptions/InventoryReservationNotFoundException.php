<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class InventoryReservationNotFoundException extends Exception
{
    public function __construct(
        public readonly ?int $reservationId = null,
        public readonly ?int $orderId = null,
    ) {
        if ($reservationId !== null) {
            $message = "Inventory reservation {$reservationId} was not found.";
        } elseif ($orderId !== null) {
            $message = "No inventory reservation found for order {$orderId}.";
        } else {
            $message = 'Inventory reservation was not found.';
        }

        parent::__construct($message);
    }
}
