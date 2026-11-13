<?php

namespace App\Domains\Checkout\DTOs;

final class PlaceOrderResult
{
    public function __construct(
        public int $orderId,
        public string $publicUuid,
        public string $orderNumber,
        public int $paymentId,
        public string $grandTotal,
        public string $currencyCode,
        public bool $idempotentReplay,
    ) {}
}
