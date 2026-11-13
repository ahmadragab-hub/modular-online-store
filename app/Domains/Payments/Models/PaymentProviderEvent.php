<?php

namespace App\Domains\Payments\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider',
    'provider_event_id',
    'event_type',
    'payment_id',
    'payload',
    'processed_at',
    'processing_result',
    'created_at',
])]
class PaymentProviderEvent extends Model
{
    public const PROCESSING_RESULT_SUCCESS = 'success';

    public const PROCESSING_RESULT_IGNORED = 'ignored';

    public const PROCESSING_RESULT_ERROR = 'error';

    public const UPDATED_AT = null;

    protected $table = 'payment_provider_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
