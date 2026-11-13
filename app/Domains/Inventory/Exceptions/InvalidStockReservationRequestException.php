<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class InvalidStockReservationRequestException extends Exception
{
    public const EMPTY_LINES = 'empty_lines';

    public const CART_NOT_FOUND = 'cart_not_found';

    public const CART_NOT_ACTIVE = 'cart_not_active';

    public const CART_EXPIRED = 'cart_expired';

    public const DUPLICATE_VARIANT = 'duplicate_variant';

    public const INVALID_QUANTITY = 'invalid_quantity';

    public const INVALID_EXPIRY = 'invalid_expiry';

    public const STALE_ACTIVE_RESERVATIONS = 'stale_active_reservations';

    public const ACTIVE_RESERVATIONS_LINKED_TO_ORDER = 'active_reservations_linked_to_order';

    public const INVENTORY_COUNTERS_INCONSISTENT = 'inventory_counters_inconsistent';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
