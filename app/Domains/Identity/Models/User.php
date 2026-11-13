<?php

namespace App\Domains\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domains\Store\Models\CurrencyExchangeRate;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_CUSTOMER = 'customer';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * @return HasMany<CurrencyExchangeRate, $this>
     */
    public function createdCurrencyExchangeRates(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class, 'created_by_user_id');
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
