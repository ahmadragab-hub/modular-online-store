<?php

namespace App\Http\Requests\Web\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ProductTranslationRequest extends FormRequest
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
            'en_name' => ['required', 'string', 'max:255'],
            'en_description' => ['nullable', 'string'],
            'ar_name' => ['required', 'string', 'max:255'],
            'ar_description' => ['nullable', 'string'],
        ];
    }
}
