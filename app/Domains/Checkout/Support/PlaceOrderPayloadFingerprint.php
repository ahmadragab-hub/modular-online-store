<?php

namespace App\Domains\Checkout\Support;

use App\Domains\Checkout\DTOs\PlaceOrderInput;
use App\Domains\Orders\Models\Order;

final class PlaceOrderPayloadFingerprint
{
    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $lines
     */
    public function fromPlaceOrderInput(PlaceOrderInput $input, array $lines): string
    {
        return $this->hash($this->canonicalPayload(
            cartId: $input->cartId,
            transactionCurrencyCode: $input->transactionCurrencyCode,
            contentLocale: $input->contentLocale,
            guestEmail: $input->guestEmail,
            shippingAddress: $input->shippingAddress,
            billingAddress: $input->billingAddress,
            customerNote: $input->customerNote,
            lines: $lines,
        ));
    }

    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $lines
     */
    public function fromOrder(Order $order, int $cartId, array $lines): string
    {
        return $this->hash($this->canonicalPayload(
            cartId: $cartId,
            transactionCurrencyCode: $order->currency_code,
            contentLocale: $order->content_locale,
            guestEmail: $order->guest_email,
            shippingAddress: $order->shipping_address,
            billingAddress: $order->billing_address,
            customerNote: $order->customer_note,
            lines: $lines,
        ));
    }

    /**
     * @param  array<string, mixed>  $shippingAddress
     * @param  array<string, mixed>  $billingAddress
     * @param  list<array{product_variant_id: int, quantity: int}>  $lines
     * @return array<string, mixed>
     */
    private function canonicalPayload(
        int $cartId,
        string $transactionCurrencyCode,
        string $contentLocale,
        ?string $guestEmail,
        array $shippingAddress,
        array $billingAddress,
        ?string $customerNote,
        array $lines,
    ): array {
        usort($lines, fn (array $a, array $b): int => $a['product_variant_id'] <=> $b['product_variant_id']);

        return [
            'billing' => $this->normalizeAddress($billingAddress),
            'cart_id' => $cartId,
            'content_locale' => $contentLocale,
            'currency' => strtoupper($transactionCurrencyCode),
            'customer_note' => trim((string) ($customerNote ?? '')),
            'guest_email' => strtolower(trim((string) ($guestEmail ?? ''))),
            'lines' => array_map(
                static fn (array $line): array => [
                    'product_variant_id' => (int) $line['product_variant_id'],
                    'quantity' => (int) $line['quantity'],
                ],
                $lines,
            ),
            'shipping' => $this->normalizeAddress($shippingAddress),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function normalizeAddress(array $address): array
    {
        ksort($address);

        $normalized = [];
        foreach ($address as $key => $value) {
            $normalized[(string) $key] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
