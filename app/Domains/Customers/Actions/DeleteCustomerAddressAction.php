<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerAddressAction
{
    public function execute(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

            $wasDefaultShipping = $address->is_default_shipping;
            $wasDefaultBilling = $address->is_default_billing;

            $address->delete();

            if (! $wasDefaultShipping && ! $wasDefaultBilling) {
                return;
            }

            /** @var CustomerAddress|null $replacement */
            $replacement = CustomerAddress::query()
                ->where('customer_id', $customer->getKey())
                ->orderBy('id')
                ->first();

            if ($replacement === null) {
                return;
            }

            $updates = [];

            if ($wasDefaultShipping && ! $replacement->is_default_shipping) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->getKey())
                    ->update(['is_default_shipping' => false]);
                $updates['is_default_shipping'] = true;
            }

            if ($wasDefaultBilling && ! $replacement->is_default_billing) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->getKey())
                    ->update(['is_default_billing' => false]);
                $updates['is_default_billing'] = true;
            }

            if ($updates !== []) {
                $replacement->update($updates);
            }
        });
    }
}
