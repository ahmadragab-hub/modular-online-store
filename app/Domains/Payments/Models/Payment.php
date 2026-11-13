<?php

namespace App\Domains\Payments\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Store\Models\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'attempt_number',
    'amount',
    'currency_code',
    'status',
    'provider',
    'provider_payment_id',
    'provider_checkout_url',
    'idempotency_key',
    'failure_code',
    'failure_message',
    'metadata',
    'authorized_at',
    'captured_at',
    'failed_at',
])]
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'payments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'amount' => 'decimal:4',
            'metadata' => 'array',
            'authorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Transaction currency for this payment attempt (frozen snapshot).
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * @return HasMany<PaymentProviderEvent, $this>
     */
    public function providerEvents(): HasMany
    {
        return $this->hasMany(PaymentProviderEvent::class);
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeCaptured(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CAPTURED);
    }
}
