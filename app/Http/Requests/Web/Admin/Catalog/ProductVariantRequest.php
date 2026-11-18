<?php

namespace App\Http\Requests\Web\Admin\Catalog;

use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
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
        $variantId = $this->route('variant')?->getKey();

        return [
            'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'size_id' => ['required', 'integer', 'exists:sizes,id'],
            'color_id' => ['required', 'integer', 'exists:colors,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in([
                ProductVariant::STATUS_ACTIVE,
                ProductVariant::STATUS_INACTIVE,
            ])],
        ];
    }
}
