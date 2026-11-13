<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
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
     * Cart and Order Eloquent relationships are deferred until those domain models exist.
     * Columns `cart_id` and `order_id` remain fillable for persistence and future belongsTo wiring.
     */

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
