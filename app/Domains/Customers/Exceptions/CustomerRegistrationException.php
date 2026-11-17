<?php

namespace App\Domains\Customers\Exceptions;

use Exception;

final class CustomerRegistrationException extends Exception
{
    public const EMAIL_TAKEN = 'email_taken';

    public const CUSTOMER_ALREADY_REGISTERED = 'customer_already_registered';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
