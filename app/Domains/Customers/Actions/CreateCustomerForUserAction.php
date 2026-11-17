<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;

final class CreateCustomerForUserAction
{
    public function execute(User $user, string $email, string $firstName, string $lastName): Customer
    {
        return Customer::query()->create([
            'user_id' => $user->getKey(),
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }
}
