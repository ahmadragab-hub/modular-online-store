<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerProfileAction
{
    public function execute(Customer $customer, User $user, string $firstName, string $lastName): Customer
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $name = trim($firstName.' '.$lastName);

        return DB::transaction(function () use ($customer, $user, $firstName, $lastName, $name): Customer {
            $customer->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            $user->update([
                'name' => $name,
            ]);

            return $customer->fresh();
        });
    }
}
