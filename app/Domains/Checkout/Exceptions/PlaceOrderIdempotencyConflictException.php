<?php

namespace App\Domains\Checkout\Exceptions;

use Exception;

final class PlaceOrderIdempotencyConflictException extends Exception
{
    public function __construct(
        public readonly string $idempotencyKey,
    ) {
        parent::__construct('Checkout idempotency key was already used with a different payload.');
    }
}
