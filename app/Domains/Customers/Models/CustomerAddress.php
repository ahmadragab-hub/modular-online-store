<?php

namespace App\Domains\Customers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'label',
    'first_name',
    'last_name',
    'phone',
    'line1',
    'line2',
    'city',
    'region',
    'postal_code',
    'country_code',
    'is_default_shipping',
    'is_default_billing',
])]
class CustomerAddress extends Model
{
    protected $table = 'customer_addresses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
