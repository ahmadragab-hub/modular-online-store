<?php

namespace App\Domains\Customers\Queries;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;

final class FindCustomerForUserQuery
{
    public function execute(User $user): ?Customer
    {
        return Customer::query()->where('user_id', $user->getKey())->first();
    }
}
