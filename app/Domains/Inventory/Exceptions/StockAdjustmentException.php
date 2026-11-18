<?php

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

final class StockAdjustmentException extends RuntimeException
{
    public const LEVEL_NOT_FOUND = 'level_not_found';

    public const INSUFFICIENT_ON_HAND = 'insufficient_on_hand';

    public const INVALID_DELTA = 'invalid_delta';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
