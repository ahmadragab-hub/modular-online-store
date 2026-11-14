<?php

namespace App\Domains\Catalog\Exceptions;

use Exception;

class VariantUnavailableForCartException extends Exception
{
    public const NOT_FOUND = 'variant_not_found';

    public const INACTIVE = 'variant_inactive';

    public const PRODUCT_UNAVAILABLE = 'product_unavailable';

    public const TRANSLATION_MISSING = 'translation_missing';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
