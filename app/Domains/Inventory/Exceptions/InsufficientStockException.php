<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product variant %d. Requested %d, available %d.',
            $productVariantId,
            $requestedQuantity,
            $availableQuantity,
        ));
    }
}
