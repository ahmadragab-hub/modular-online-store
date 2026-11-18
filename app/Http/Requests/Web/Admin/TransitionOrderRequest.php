<?php

namespace App\Http\Requests\Web\Admin;

use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionOrderRequest extends FormRequest
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
            'to_status' => ['required', 'string', Rule::in([
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
            ])],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
