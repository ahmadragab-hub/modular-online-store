<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id',
    'type',
    'quantity_delta',
    'quantity_on_hand_after',
    'reference_type',
    'reference_id',
    'notes',
    'created_by_user_id',
    'created_at',
])]
class InventoryMovement extends Model
{
    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';

    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';

    public const TYPE_RESERVATION_HOLD = 'reservation_hold';

    public const TYPE_RESERVATION_RELEASE = 'reservation_release';

    public const TYPE_SALE_COMMIT = 'sale_commit';

    public const TYPE_RETURN_RESTOCK = 'return_restock';

    public const UPDATED_AT = null;

    protected $table = 'inventory_movements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'quantity_on_hand_after' => 'integer',
            'reference_id' => 'integer',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
