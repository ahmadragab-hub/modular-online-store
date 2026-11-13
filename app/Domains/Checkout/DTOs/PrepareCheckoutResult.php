<?php

namespace App\Domains\Checkout\DTOs;

final class PrepareCheckoutResult
{
    /**
     * @param  list<PrepareCheckoutLineResult>  $lines
     */
    public function __construct(
        public int $cartId,
        public string $transactionCurrencyCode,
        public string $baseCurrencyCode,
        public string $exchangeRate,
        public ?int $currencyExchangeRateId,
        public ?string $exchangeRateEffectiveFrom,
        public bool $taxEnabled,
        public string $subtotal,
        public string $discountTotal,
        public string $shippingTotal,
        public string $taxTotal,
        public string $grandTotal,
        public string $subtotalBaseUsd,
        public array $lines,
    ) {}
}
