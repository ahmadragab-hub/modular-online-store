<?php

namespace App\Domains\Checkout\Exceptions;

use Exception;

class PlaceOrderException extends Exception
{
    public const GUEST_CHECKOUT_DISABLED = 'guest_checkout_disabled';

    public const INVALID_CONTENT_LOCALE = 'invalid_content_locale';

    public const IDEMPOTENCY_KEY_REQUIRED = 'idempotency_key_required';

    public const ORDER_NOT_REPLAYABLE = 'order_not_replayable';

    public const GUEST_EMAIL_REQUIRED = 'guest_email_required';

    public const GUEST_EMAIL_CONFLICT = 'guest_email_conflict';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
