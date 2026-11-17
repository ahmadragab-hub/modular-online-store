<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

final class SetCustomerDefaultShippingAddressAction
{
    public function execute(Customer $customer, CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address): CustomerAddress {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

            CustomerAddress::query()
                ->where('customer_id', $customer->getKey())
                ->update(['is_default_shipping' => false]);

            $address->update(['is_default_shipping' => true]);

            return $address->refresh();
        });
    }
}
