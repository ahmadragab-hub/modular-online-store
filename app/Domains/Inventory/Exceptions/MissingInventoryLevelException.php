<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class MissingInventoryLevelException extends Exception
{
    /**
     * @param  list<int>  $missingProductVariantIds
     */
    public function __construct(
        public readonly array $missingProductVariantIds,
    ) {
        $ids = implode(', ', array_map('strval', $missingProductVariantIds));

        parent::__construct("Inventory level not found for product variant id(s): {$ids}.");
    }
}
