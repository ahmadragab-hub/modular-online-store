<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id',
    'quantity',
    'status',
    'cart_id',
    'order_id',
    'expires_at',
    'committed_at',
    'released_at',
])]
class InventoryReservation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'inventory_reservations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @param  Builder<InventoryReservation>  $query
     * @return Builder<InventoryReservation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<InventoryReservation>  $query
     * @return Builder<InventoryReservation>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
