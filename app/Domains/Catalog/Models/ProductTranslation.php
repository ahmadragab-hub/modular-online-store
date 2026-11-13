<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Store\Models\StoreLocale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'locale',
    'name',
    'short_description',
    'description',
])]
class ProductTranslation extends Model
{
    protected $table = 'product_translations';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<StoreLocale, $this>
     */
    public function storeLocale(): BelongsTo
    {
        return $this->belongsTo(StoreLocale::class, 'locale', 'code');
    }
}
