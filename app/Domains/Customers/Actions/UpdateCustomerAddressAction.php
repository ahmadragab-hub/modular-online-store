<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerAddressAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(CustomerAddress $address, array $attributes): CustomerAddress
    {
        return DB::transaction(function () use ($address, $attributes): CustomerAddress {
            $address->fill([
                'label' => $attributes['label'] ?? null,
                'first_name' => trim((string) $attributes['first_name']),
                'last_name' => trim((string) $attributes['last_name']),
                'phone' => isset($attributes['phone']) ? trim((string) $attributes['phone']) : null,
                'line1' => trim((string) $attributes['line1']),
                'line2' => isset($attributes['line2']) ? trim((string) $attributes['line2']) : null,
                'city' => trim((string) $attributes['city']),
                'region' => isset($attributes['region']) ? trim((string) $attributes['region']) : null,
                'postal_code' => trim((string) $attributes['postal_code']),
                'country_code' => strtoupper(trim((string) $attributes['country_code'])),
            ]);
            $address->save();

            return $address->refresh();
        });
    }
}
