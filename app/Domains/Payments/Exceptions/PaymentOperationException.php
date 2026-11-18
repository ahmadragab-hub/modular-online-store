<?php

namespace App\Domains\Payments\Exceptions;

use RuntimeException;

final class PaymentOperationException extends RuntimeException
{
    public const PAYMENT_NOT_FOUND = 'payment_not_found';

    public const INVALID_STATE = 'invalid_state';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
