<?php

namespace App\Domains\Orders\Models;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Store\Models\Currency;
use App\Domains\Store\Models\CurrencyExchangeRate;
use App\Domains\Store\Models\StoreLocale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_uuid',
    'order_number',
    'customer_id',
    'user_id',
    'guest_email',
    'status',
    'base_currency_code',
    'currency_code',
    'content_locale',
    'exchange_rate',
    'currency_exchange_rate_id',
    'exchange_rate_effective_from',
    'subtotal',
    'discount_total',
    'tax_total',
    'shipping_total',
    'grand_total',
    'shipping_address',
    'billing_address',
    'customer_note',
    'idempotency_key',
    'placed_at',
    'paid_at',
    'cancelled_at',
])]
class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'orders';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'exchange_rate_effective_from' => 'datetime',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'shipping_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_code', 'code');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function transactionCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * @return BelongsTo<StoreLocale, $this>
     */
    public function contentLocale(): BelongsTo
    {
        return $this->belongsTo(StoreLocale::class, 'content_locale', 'code');
    }

    /**
     * @return BelongsTo<CurrencyExchangeRate, $this>
     */
    public function currencyExchangeRate(): BelongsTo
    {
        return $this->belongsTo(CurrencyExchangeRate::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * @return HasMany<InventoryReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePaymentFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAYMENT_FAILED);
    }
}
