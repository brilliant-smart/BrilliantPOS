<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'name'                => ['sometimes', 'string', 'max:255'],
            'slug'                => ['sometimes', 'string', 'max:255', Rule::unique('products')->whereNull('deleted_at')->ignore($productId)],
            'sku'                 => ['sometimes', 'string', 'max:100', Rule::unique('products')->whereNull('deleted_at')->ignore($productId)],
            'barcodes'            => ['nullable', 'array'],
            'barcodes.*'          => ['nullable', 'string', 'max:100'],
            'description'         => ['nullable', 'string'],
            'price'               => ['sometimes', 'numeric', 'min:0'],
            'cost_price'          => ['nullable', 'numeric', 'min:0'],
            'stock_quantity'      => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'image'               => ['nullable', 'image', 'max:2048'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'batch_number'        => ['nullable', 'string', 'max:255'],
            'manufacturing_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:today'],
            'unit_types'          => ['nullable', 'array'],
            'unit_types.*.id'     => ['nullable', 'integer', 'exists:product_unit_types,id'],
            'unit_types.*.name'   => ['required_with:unit_types', 'string', 'max:50'],
            'unit_types.*.short_name' => ['nullable', 'string', 'max:10'],
            'unit_types.*.conversion_factor' => ['required_with:unit_types', 'numeric', 'min:0.01'],
            'unit_types.*.selling_price' => ['required_with:unit_types', 'numeric', 'min:0'],
            'unit_types.*.barcodes' => ['nullable', 'array'],
            'unit_types.*.barcodes.*' => ['nullable', 'string', 'max:100'],
            'unit_types.*.is_base' => ['nullable', 'boolean'],
            'unit_types.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}