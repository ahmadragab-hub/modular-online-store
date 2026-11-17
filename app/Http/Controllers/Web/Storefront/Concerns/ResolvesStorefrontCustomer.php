<?php

namespace App\Http\Controllers\Web\Storefront\Concerns;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Customers\Queries\FindCustomerForUserQuery;
use Illuminate\Support\Facades\Auth;

trait ResolvesStorefrontCustomer
{
    protected function currentCustomer(): Customer
    {
        $user = Auth::user();

        if ($user === null) {
            abort(403);
        }

        $customer = app(FindCustomerForUserQuery::class)->execute($user);

        if ($customer === null) {
            abort(403);
        }

        return $customer;
    }

    protected function currentCustomerAddress(CustomerAddress $address): CustomerAddress
    {
        $customer = $this->currentCustomer();

        if ((int) $address->customer_id !== (int) $customer->getKey()) {
            abort(404);
        }

        return $address;
    }
}
