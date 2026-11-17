<?php

namespace App\Domains\Identity\Exceptions;

use Exception;

final class CustomerLoginException extends Exception
{
    public function __construct()
    {
        parent::__construct('Customer login failed.');
    }
}
