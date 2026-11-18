<?php

namespace App\Domains\Orders\Exceptions;

use RuntimeException;

final class OrderTransitionException extends RuntimeException
{
    public const INVALID_STATUS = 'invalid_status';

    public const FORBIDDEN_TRANSITION = 'forbidden_transition';

    public const ORDER_NOT_FOUND = 'order_not_found';

    public const RESERVATIONS_NOT_COMMITTABLE = 'reservations_not_committable';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
