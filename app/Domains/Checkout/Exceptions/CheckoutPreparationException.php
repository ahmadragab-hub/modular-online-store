<?php

namespace App\Domains\Checkout\Exceptions;

use Exception;

class CheckoutPreparationException extends Exception
{
    public const CART_NOT_FOUND = 'cart_not_found';

    public const CART_NOT_ACTIVE = 'cart_not_active';

    public const CART_EXPIRED = 'cart_expired';

    public const CART_EMPTY = 'cart_empty';

    public const CART_OWNERSHIP = 'cart_ownership_denied';

    public const VARIANT_UNAVAILABLE = 'variant_unavailable';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    public const CURRENCY_UNSUPPORTED = 'currency_unsupported';

    public const CURRENCY_NOT_TRANSACTION_ENABLED = 'currency_not_transaction_enabled';

    public const EXCHANGE_RATE_MISSING = 'exchange_rate_missing';

    public const STORE_SETTINGS_MISSING = 'store_settings_missing';

    public const SHIPPING_NOT_CONFIGURED = 'shipping_not_configured';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
