<?php

namespace App\Domains\Store\Exceptions;

use RuntimeException;

final class StoreAdminException extends RuntimeException
{
    public const SETTINGS_NOT_FOUND = 'settings_not_found';

    public const INVALID_CURRENCY = 'invalid_currency';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
