<?php

namespace App\Domains\Payments\Exceptions;

use RuntimeException;

final class PaymentGatewayException extends RuntimeException
{
    public const NOT_OPERATIONAL = 'not_operational';

    public const INITIATION_FAILED = 'initiation_failed';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
