<?php

namespace App\Http\Requests\Web;

use App\Domains\Checkout\DTOs\PlaceOrderInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cart_id' => ['required', 'integer', 'min:1'],
            'transaction_currency_code' => ['required', 'string', 'size:3'],
            'content_locale' => ['required', 'string', 'max:5'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'guest_email' => [Rule::requiredIf(fn (): bool => ! Auth::check()), 'nullable', 'email', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'session_token' => ['nullable', 'string', 'max:64'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.first_name' => ['required', 'string', 'max:100'],
            'shipping_address.last_name' => ['required', 'string', 'max:100'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.country_code' => ['required', 'string', 'size:2'],
            'billing_address' => ['required', 'array'],
            'billing_address.first_name' => ['required', 'string', 'max:100'],
            'billing_address.last_name' => ['required', 'string', 'max:100'],
            'billing_address.line1' => ['required', 'string', 'max:255'],
            'billing_address.city' => ['required', 'string', 'max:120'],
            'billing_address.country_code' => ['required', 'string', 'size:2'],
        ];
    }

    public function toPlaceOrderInput(): PlaceOrderInput
    {
        $validated = $this->validated();
        $userId = Auth::id();
        $sessionToken = $validated['session_token'] ?? $this->session()->get('cart_session_token');

        return new PlaceOrderInput(
            cartId: (int) $validated['cart_id'],
            transactionCurrencyCode: strtoupper($validated['transaction_currency_code']),
            contentLocale: $validated['content_locale'],
            idempotencyKey: $validated['idempotency_key'],
            shippingAddress: $validated['shipping_address'],
            billingAddress: $validated['billing_address'],
            guestEmail: $validated['guest_email'] ?? null,
            customerNote: $validated['customer_note'] ?? null,
            userId: $userId !== null ? (int) $userId : null,
            sessionToken: is_string($sessionToken) ? $sessionToken : null,
        );
    }
}
