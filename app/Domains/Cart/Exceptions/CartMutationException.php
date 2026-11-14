<?php

namespace App\Domains\Cart\Exceptions;

use Exception;

class CartMutationException extends Exception
{
    public const CART_NOT_ACTIVE = 'cart_not_active';

    public const CART_EXPIRED = 'cart_expired';

    public const CART_ITEM_NOT_FOUND = 'cart_item_not_found';

    public const VARIANT_UNAVAILABLE = 'variant_unavailable';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    public const INVALID_QUANTITY = 'invalid_quantity';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
