<?php

namespace App\Domains\Customers\Models;

use App\Domains\Cart\Models\Cart;
use App\Domains\Identity\Models\User;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\StoreLocale;
use App\Domains\Store\Models\StoreTheme;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'email',
    'first_name',
    'last_name',
    'phone',
    'marketing_opt_in',
    'preferred_display_currency_code',
    'preferred_locale',
    'preferred_theme',
])]
class Customer extends Model
{
    protected $table = 'customers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function preferredDisplayCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'preferred_display_currency_code', 'code');
    }

    /**
     * @return BelongsTo<StoreLocale, $this>
     */
    public function preferredLocale(): BelongsTo
    {
        return $this->belongsTo(StoreLocale::class, 'preferred_locale', 'code');
    }

    /**
     * @return BelongsTo<StoreTheme, $this>
     */
    public function preferredTheme(): BelongsTo
    {
        return $this->belongsTo(StoreTheme::class, 'preferred_theme', 'code');
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeRegistered(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeGuestIdentity(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }
}
