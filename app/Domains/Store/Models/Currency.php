<?php

namespace App\Domains\Store\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'symbol',
    'minor_units',
    'is_base',
    'display_enabled',
    'transaction_enabled',
    'sort_order',
])]
class Currency extends Model
{
    protected $table = 'currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minor_units' => 'integer',
            'is_base' => 'boolean',
            'display_enabled' => 'boolean',
            'transaction_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<CurrencyExchangeRate, $this>
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class, 'quote_currency_code', 'code');
    }
}
