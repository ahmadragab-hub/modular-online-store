<?php

namespace App\Domains\Cart\DTOs;

final readonly class AddCartItemMergeLineResult
{
    public const STATUS_MERGED = 'merged';

    public const STATUS_CAPPED = 'capped';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_NO_STOCK = 'no_stock';

    public function __construct(
        public string $status,
        public int $productVariantId,
        public string $productName,
        public int $guestQuantityRequested,
        public int $quantityAdded,
        public int $finalQuantity,
    ) {}

    public function appliedAnyQuantity(): bool
    {
        return $this->quantityAdded > 0;
    }
}
