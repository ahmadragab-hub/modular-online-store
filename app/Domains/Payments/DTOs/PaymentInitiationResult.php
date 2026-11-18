<?php

namespace App\Domains\Payments\DTOs;

final class PaymentInitiationResult
{
    public function __construct(
        public string $provider,
        public string $providerPaymentId,
        public string $redirectUrl,
        public bool $reusedExistingSession = false,
    ) {}
}
