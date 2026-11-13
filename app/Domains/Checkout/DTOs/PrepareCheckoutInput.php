<?php

namespace App\Domains\Checkout\DTOs;

final class PrepareCheckoutInput
{
    public function __construct(
        public int $cartId,
        public string $transactionCurrencyCode,
        public ?int $userId = null,
        public ?string $sessionToken = null,
    ) {}
}
