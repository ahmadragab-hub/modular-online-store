<?php

namespace App\Domains\Store\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_name',
    'contact_email',
    'contact_phone',
    'base_currency_code',
    'default_display_currency_code',
    'timezone',
    'address_line1',
    'address_line2',
    'city',
    'region',
    'postal_code',
    'country_code',
    'tax_enabled',
    'prices_include_tax',
    'default_tax_rate',
    'tax_configuration',
    'shipping_enabled',
    'flat_shipping_amount',
    'free_shipping_threshold',
    'order_number_prefix',
    'reservation_ttl_minutes',
    'guest_checkout_enabled',
    'default_locale',
    'default_theme',
])]
class StoreSetting extends Model
{
    public const SINGLETON_ID = 1;

    protected $table = 'store_settings';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'prices_include_tax' => 'boolean',
            'default_tax_rate' => 'decimal:4',
            'tax_configuration' => 'array',
            'shipping_enabled' => 'boolean',
            'flat_shipping_amount' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'reservation_ttl_minutes' => 'integer',
            'guest_checkout_enabled' => 'boolean',
        ];
    }

    public static function singleton(): ?self
    {
        return static::query()->find(self::SINGLETON_ID);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_code', 'code');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function defaultDisplayCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_display_currency_code', 'code');
    }

    /**
     * @return BelongsTo<StoreLocale, $this>
     */
    public function defaultLocale(): BelongsTo
    {
        return $this->belongsTo(StoreLocale::class, 'default_locale', 'code');
    }

    /**
     * @return BelongsTo<StoreTheme, $this>
     */
    public function defaultTheme(): BelongsTo
    {
        return $this->belongsTo(StoreTheme::class, 'default_theme', 'code');
    }
}
