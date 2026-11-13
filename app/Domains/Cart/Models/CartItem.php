<?php

namespace App\Domains\Cart\Models;

use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_id',
    'product_variant_id',
    'quantity',
    'unit_price_base',
    'product_name',
    'sku',
    'variant_label',
])]
class CartItem extends Model
{
    protected $table = 'cart_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_base' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
