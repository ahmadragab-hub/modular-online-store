<?php

namespace App\Domains\Checkout\DTOs;

final class PlaceOrderInput
{
    /**
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>  $billingAddress
     */
    public function __construct(
        public int $cartId,
        public string $transactionCurrencyCode,
        public string $contentLocale,
        public string $idempotencyKey,
        public array $shippingAddress,
        public array $billingAddress,
        public ?string $guestEmail = null,
        public ?string $customerNote = null,
        public ?int $userId = null,
        public ?string $sessionToken = null,
    ) {}
}
