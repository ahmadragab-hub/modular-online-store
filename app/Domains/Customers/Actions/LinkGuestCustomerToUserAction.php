<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Exceptions\CustomerRegistrationException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;

final class LinkGuestCustomerToUserAction
{
    public function execute(
        Customer $customer,
        User $user,
        string $firstName,
        string $lastName,
    ): Customer {
        if ($customer->user_id !== null) {
            throw new CustomerRegistrationException(
                CustomerRegistrationException::CUSTOMER_ALREADY_REGISTERED,
                'A customer account is already registered for this email.',
            );
        }

        $customer->fill([
            'user_id' => $user->getKey(),
            'email' => strtolower(trim((string) $user->email)),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        $customer->save();

        return $customer->refresh();
    }
}
