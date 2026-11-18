<?php

namespace App\Domains\Catalog\Exceptions;

use RuntimeException;

final class CatalogAdminException extends RuntimeException
{
    public const NOT_FOUND = 'not_found';

    public const VALIDATION = 'validation';

    public const DUPLICATE_SKU = 'duplicate_sku';

    public const FORBIDDEN = 'forbidden';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
