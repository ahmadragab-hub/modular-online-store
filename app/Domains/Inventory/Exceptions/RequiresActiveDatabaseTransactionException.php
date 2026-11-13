<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

final class RequiresActiveDatabaseTransactionException extends Exception
{
    public function __construct()
    {
        parent::__construct('Stock reservation must run inside an active database transaction.');
    }
}
