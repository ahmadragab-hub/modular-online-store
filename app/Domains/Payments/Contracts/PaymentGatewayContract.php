<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\PaymentInitiationResult;
use App\Domains\Payments\Models\Payment;

interface PaymentGatewayContract
{
    public function providerKey(): string;

    public function initiateCheckout(Payment $payment, Order $order): PaymentInitiationResult;

    public function assertOperational(): void;
}
