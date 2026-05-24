<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'payment_method' => 'nullable|in:cash,bank_transfer,cheque,card,credit,credit_7,credit_14,credit_30,credit_60',
            'payment_due_date' => 'nullable|date',
            'status' => 'nullable|in:draft,pending',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.unit_type' => 'nullable|string|in:piece,carton,box,pack,dozen,bag,crate,bundle,sack,kg,liter,meter',
            'items.*.product_unit_type_id' => 'nullable|integer|exists:product_unit_types,id',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.01',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
            'items.*.batch_number' => 'nullable|string|max:255',
            'items.*.manufacturing_date' => 'nullable|date|before_or_equal:today',
            'items.*.expiry_date' => 'nullable|date',
        ];
    }
}