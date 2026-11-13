<?php

namespace App\Domains\Checkout\DTOs;

final class PrepareCheckoutLineResult
{
    public function __construct(
        public int $cartItemId,
        public int $productVariantId,
        public string $sku,
        public string $productName,
        public string $variantLabel,
        public int $quantity,
        public string $unitPriceBaseUsd,
        public string $unitPrice,
        public string $lineSubtotal,
    ) {}
}
