<?php

namespace App\Domains\Cart\Exceptions;

use Exception;

final class MultipleActiveCustomerCartsException extends Exception
{
    /**
     * @param  list<int>  $cartIds
     */
    public function __construct(
        public readonly array $cartIds,
        string $message = 'Multiple active carts exist for this customer.',
    ) {
        parent::__construct($message);
    }
}
