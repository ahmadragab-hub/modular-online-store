<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreSettingsRequest extends FormRequest
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
            'store_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'shipping_enabled' => ['sometimes', 'boolean'],
            'flat_shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0'],
            'order_number_prefix' => ['required', 'string', 'max:20'],
            'reservation_ttl_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'guest_checkout_enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_enabled' => $this->boolean('shipping_enabled'),
            'guest_checkout_enabled' => $this->boolean('guest_checkout_enabled'),
        ]);
    }
}
