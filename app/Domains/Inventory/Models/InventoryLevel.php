<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id',
    'quantity_on_hand',
    'quantity_reserved',
    'low_stock_threshold',
    'version',
])]
class InventoryLevel extends Model
{
    public const CREATED_AT = null;

    protected $table = 'inventory_levels';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'low_stock_threshold' => 'integer',
            'version' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
