<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_type' => 'nullable|string',
            'items.*.product_unit_type_id' => 'nullable|integer|exists:product_unit_types,id',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',

            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,pos,credit,bank_transfer',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',

            'customer_name' => 'nullable|string|max:255',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}