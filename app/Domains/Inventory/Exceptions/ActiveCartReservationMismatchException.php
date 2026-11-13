<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class ActiveCartReservationMismatchException extends Exception
{
    /**
     * @param  array<int, int>  $requestedQuantitiesByVariant
     * @param  array<int, int>  $activeQuantitiesByVariant
     */
    public function __construct(
        public readonly int $cartId,
        public readonly array $requestedQuantitiesByVariant,
        public readonly array $activeQuantitiesByVariant,
    ) {
        parent::__construct(sprintf(
            'Cart %d already has active reservations that do not match the requested variant quantities.',
            $cartId,
        ));
    }
}
