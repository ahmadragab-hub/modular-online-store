<?php

namespace App\Domains\Cart\Exceptions;

use Exception;

final class CartMergeException extends Exception
{
    public const FAILED = 'cart_merge_failed';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
