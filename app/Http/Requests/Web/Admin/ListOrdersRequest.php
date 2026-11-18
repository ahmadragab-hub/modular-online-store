<?php

namespace App\Http\Requests\Web\Admin;

use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in([
                Order::STATUS_PENDING_PAYMENT,
                Order::STATUS_PAYMENT_FAILED,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ])],
            'order_number' => ['nullable', 'string', 'max:100'],
            'customer_email' => ['nullable', 'string', 'max:255'],
        ];
    }
}
